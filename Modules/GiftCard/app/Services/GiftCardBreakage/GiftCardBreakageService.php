<?php

namespace Modules\GiftCard\Services\GiftCardBreakage;

use App\Forkiva;
use Brick\Math\BigDecimal;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Modules\Branch\Models\Branch;
use Modules\Branch\Services\BranchAccess\BranchAccessServiceInterface;
use Modules\Core\Contracts\FinancialPostingBridgeInterface;
use Modules\GiftCard\Enums\GiftCardBreakageBatchStatus;
use Modules\GiftCard\Enums\GiftCardStatus;
use Modules\GiftCard\Enums\GiftCardTransactionType;
use Modules\GiftCard\Models\GiftCard;
use Modules\GiftCard\Models\GiftCardBreakageBatch;
use Modules\GiftCard\Services\GiftCardTransaction\GiftCardTransactionServiceInterface;
use Modules\GiftCard\Support\GiftCardCurrencyRate;
use Throwable;

/**
 * Coordinates gift card breakage review, posting, export queries, and reversals.
 *
 * The service keeps breakage accounting auditable by freezing source balances on
 * draft/review, validating card state again before posting, and writing reversal
 * batches instead of mutating posted batches in place.
 */
class GiftCardBreakageService implements GiftCardBreakageServiceInterface
{
    public function __construct(
        protected GiftCardTransactionServiceInterface $transactions,
        protected FinancialPostingBridgeInterface $postingBridge,
        protected BranchAccessServiceInterface $branchAccess,
    ) {}

    /** {@inheritDoc} */
    public function exportResourceKey(): string
    {
        return 'gift_card_breakage_batches';
    }

    /** {@inheritDoc} */
    public function exportPermission(): string
    {
        return 'admin.gift_card_breakage_batches.export';
    }

    /** {@inheritDoc} */
    public function exportModelClass(): string
    {
        return GiftCardBreakageBatch::class;
    }

    /** {@inheritDoc} */
    public function show(int $id): GiftCardBreakageBatch
    {
        return GiftCardBreakageBatch::query()
            ->with($this->detailRelations())
            ->findOrFail($id);
    }

    /**
     * Get the audit and frozen-balance relationships required by the breakage
     * detail screen and its export-aware listing.
     *
     * @return array<int, string>
     */
    private function detailRelations(): array
    {
        return [
            'branch:id,name',
            'createdBy:id,name',
            'reviewedBy:id,name',
            'postedBy:id,name',
            'reversal:id,reference_no,status,reversal_of_id',
            'reversalOf:id,reference_no,status',
            'lines.giftCard:id,code,current_balance,currency,status,expiry_date',
        ];
    }

    /** {@inheritDoc} */
    public function getFormMeta(?int $branchId = null): array
    {
        $resolvedBranchId = $this->branchAccess->resolve($branchId);

        if (is_null($resolvedBranchId)) {
            return [
                'branches' => Branch::list(),
            ];
        }

        return [
            'eligible_cards' => GiftCard::list(
                liabilityBranchId: $resolvedBranchId,
                status: GiftCardStatus::Expired,
                withPositiveBalance: true,
            ),
        ];
    }

    /** {@inheritDoc} */
    public function get(array $filters = [], ?array $sorts = []): LengthAwarePaginator
    {
        return $this->exportQuery($filters, $sorts)
            ->with($this->detailRelations())
            ->paginate(Forkiva::paginate())
            ->withQueryString();
    }

    /**
     * Build the reusable breakage batch query for listings and exports.
     *
     * @param  array<string, mixed>  $filters
     * @param  array<int, array{key:string,order:string}>|null  $sorts
     * @param  array<string, mixed>  $context
     * @return Builder<GiftCardBreakageBatch>
     */
    public function exportQuery(array $filters = [], ?array $sorts = [], array $context = []): Builder
    {
        return GiftCardBreakageBatch::query()
            ->filters($filters)
            ->sortBy($sorts);
    }

    /**
     * Create a draft breakage batch from currently expired, positive-balance cards.
     *
     * The idempotency key may be replayed only when the branch, economic date,
     * review note, and selected card set match the existing draft.
     *
     * @param  array{
     *     branch_id:int,
     *     gift_card_ids:array<int, int>,
     *     economic_date:string,
     *     idempotency_key:string,
     *     review_note:string
     * }  $data
     *
     * @throws Throwable
     */
    public function store(array $data): GiftCardBreakageBatch
    {
        $data['branch_id'] = (int) $this->branchAccess->resolve(
            isset($data['branch_id']) ? (int) $data['branch_id'] : null,
            true,
        );

        return DB::transaction(function () use ($data): GiftCardBreakageBatch {
            $existing = GiftCardBreakageBatch::query()->where('idempotency_key', $data['idempotency_key'])->first();
            if ($existing) {
                $existing->loadMissing($this->detailRelations());
                $matches = (int) $existing->branch_id === (int) $data['branch_id']
                    && $existing->economic_date?->toDateString() === (string) $data['economic_date']
                    && $existing->review_note === $data['review_note']
                    && $existing->lines->pluck('gift_card_id')->map(fn ($id): int => (int) $id)->sort()->values()->all()
                        === collect($data['gift_card_ids'])->map(fn ($id): int => (int) $id)->unique()->sort()->values()->all();
                abort_unless($matches, 422, __('giftcard::messages.idempotency_key_conflict'));

                return $existing;
            }

            $cards = GiftCard::query()
                ->where('liability_branch_id', $data['branch_id'])
                ->whereIn('id', $data['gift_card_ids'])
                ->orderBy('id')
                ->lockForUpdate()
                ->get();
            abort_if($cards->count() !== count(array_unique($data['gift_card_ids'])), 422, __('giftcard::messages.breakage_requires_expired_card'));
            abort_if($cards->contains(fn (GiftCard $card) => $card->status !== GiftCardStatus::Expired
                || BigDecimal::of((string) $card->getRawOriginal('current_balance'))->isLessThanOrEqualTo(BigDecimal::zero())), 422, __('giftcard::messages.breakage_requires_expired_card'));
            $currency = (string) $cards->first()->currency;
            abort_if($cards->contains(fn (GiftCard $card) => $card->currency !== $currency), 422, __('giftcard::messages.breakage_currency_mismatch'));
            $total = $cards->reduce(fn (BigDecimal $sum, GiftCard $card) => $sum->plus((string) $card->getRawOriginal('current_balance')), BigDecimal::zero());
            $currencyRate = BigDecimal::of(GiftCardCurrencyRate::toDefault($currency));
            abort_if($currencyRate->isLessThanOrEqualTo(BigDecimal::zero()), 422, __('giftcard::messages.currency_rate_invalid'));

            $batch = GiftCardBreakageBatch::query()->create([
                'branch_id' => $data['branch_id'],
                'reference_no' => 'GCB-'.now()->format('Ymd').'-'.Str::upper(Str::random(8)),
                'status' => GiftCardBreakageBatchStatus::Draft,
                'currency' => $currency,
                'currency_rate' => (string) $currencyRate,
                'total_amount' => (string) $total,
                'economic_date' => $data['economic_date'],
                'idempotency_key' => $data['idempotency_key'],
                'review_note' => $data['review_note'],
                'source_snapshot' => ['source_version' => 1, 'gift_card_ids' => $cards->pluck('id')->all()],
            ]);
            /** @var GiftCard $card */
            foreach ($cards as $card) {
                $batch->lines()->create([
                    'gift_card_id' => $card->id,
                    'amount' => $card->getRawOriginal('current_balance'),
                    'currency' => $currency,
                    'currency_rate' => (string) $currencyRate,
                    'source_snapshot' => [
                        'gift_card_id' => $card->id,
                        'code' => $card->code,
                        'balance' => $card->getRawOriginal('current_balance'),
                        'expired_at' => $card->expiry_date?->toDateTimeString(),
                    ],
                ]);
            }

            return $batch->load($this->detailRelations());
        });
    }

    /**
     * Revalidate a draft batch and freeze final balances for posting.
     *
     * Reviewed batches are idempotent when the replayed economic date and review
     * note match the already reviewed batch.
     *
     * @param  array{economic_date:string, review_note:string}  $data
     *
     * @throws Throwable
     */
    public function review(int $id, array $data): GiftCardBreakageBatch
    {
        return DB::transaction(function () use ($id, $data): GiftCardBreakageBatch {
            $batch = GiftCardBreakageBatch::query()->with('lines')->lockForUpdate()->findOrFail($id);
            if ($batch->status === GiftCardBreakageBatchStatus::Reviewed) {
                abort_unless(
                    $batch->economic_date?->toDateString() === $data['economic_date']
                    && $batch->review_note === $data['review_note'],
                    422,
                    __('giftcard::messages.idempotency_key_conflict'),
                );

                return $batch->load($this->detailRelations());
            }
            abort_unless($batch->status === GiftCardBreakageBatchStatus::Draft, 422, __('giftcard::messages.breakage_not_draft'));
            $cards = GiftCard::query()->whereIn('id', $batch->lines->pluck('gift_card_id'))->orderBy('id')->lockForUpdate()->get()->keyBy('id');
            $total = BigDecimal::zero();
            $rate = BigDecimal::of(GiftCardCurrencyRate::toDefault($batch->currency));
            abort_if($rate->isLessThanOrEqualTo(BigDecimal::zero()), 422, __('giftcard::messages.currency_rate_invalid'));
            foreach ($batch->lines->sortBy('gift_card_id') as $line) {
                /** @var GiftCard $card */
                $card = $cards->get($line->gift_card_id);
                abort_if(! $card || $card->status !== GiftCardStatus::Expired
                    || BigDecimal::of((string) $card->getRawOriginal('current_balance'))->isLessThanOrEqualTo(BigDecimal::zero()), 422, __('giftcard::messages.breakage_requires_expired_card'));
                abort_if((int) $card->liability_branch_id !== (int) $batch->branch_id, 422, __('giftcard::messages.liability_branch_mismatch'));
                abort_if($card->currency !== $batch->currency, 422, __('giftcard::messages.breakage_currency_mismatch'));
                $amount = (string) $card->getRawOriginal('current_balance');
                $total = $total->plus($amount);
                $line->update([
                    'amount' => $amount,
                    'currency' => $batch->currency,
                    'currency_rate' => (string) $rate,
                    'source_snapshot' => [
                        'gift_card_id' => $card->id,
                        'code' => $card->code,
                        'balance' => $amount,
                        'expired_at' => $card->expiry_date?->toDateTimeString(),
                    ],
                ]);
            }
            $batch->update([
                'status' => GiftCardBreakageBatchStatus::Reviewed,
                'currency_rate' => (string) $rate,
                'total_amount' => (string) $total,
                'economic_date' => $data['economic_date'],
                'review_note' => $data['review_note'],
                'reviewed_by' => auth()->id(),
                'reviewed_at' => now(),
                'source_snapshot' => [
                    ...($batch->source_snapshot ?? []),
                    'reviewed_balances' => $batch->lines->mapWithKeys(fn ($line) => [$line->gift_card_id => $line->getRawOriginal('amount')])->all(),
                ],
            ]);

            return $batch->fresh($this->detailRelations());
        });
    }

    /**
     * Post a reviewed batch to the gift card ledger and accounting bridge.
     *
     * Each frozen line creates one breakage transaction after confirming the
     * card's current balance still matches the reviewed amount.
     *
     * @throws Throwable
     */
    public function post(int $id): GiftCardBreakageBatch
    {
        $batch = DB::transaction(function () use ($id): GiftCardBreakageBatch {
            $batch = GiftCardBreakageBatch::query()->with('lines')->lockForUpdate()->findOrFail($id);
            if ($batch->status === GiftCardBreakageBatchStatus::Posted) {
                return $batch;
            }
            abort_unless($batch->status === GiftCardBreakageBatchStatus::Reviewed, 422, __('giftcard::messages.breakage_not_reviewed'));
            $cards = GiftCard::query()->whereIn('id', $batch->lines->pluck('gift_card_id'))->orderBy('id')->lockForUpdate()->get()->keyBy('id');
            foreach ($batch->lines->sortBy('gift_card_id') as $line) {
                /** @var GiftCard $card */
                $card = $cards->get($line->gift_card_id);
                abort_if(! $card || $card->status !== GiftCardStatus::Expired || (string) $card->getRawOriginal('current_balance') !== (string) $line->getRawOriginal('amount'), 422, __('giftcard::messages.breakage_balance_changed'));
                abort_if((int) $card->liability_branch_id !== (int) $batch->branch_id, 422, __('giftcard::messages.liability_branch_mismatch'));
                $transaction = $this->transactions->record($card, [
                    'type' => GiftCardTransactionType::Breakage,
                    'amount' => $line->getRawOriginal('amount'),
                    'branch_id' => $batch->branch_id,
                    'currency_rate' => $line->currency_rate,
                    'idempotency_key' => "gift_card_breakage:$batch->id:card:$card->id",
                    'economic_date' => $batch->economic_date->toDateString(),
                    'notes' => $batch->review_note,
                ]);
                $line->update(['gift_card_transaction_id' => $transaction->id]);
            }
            $batch->update([
                'status' => GiftCardBreakageBatchStatus::Posted,
                'posted_by' => auth()->id(),
                'posted_at' => now(),
            ]);

            return $batch->fresh('lines');
        });

        $this->postingBridge->recordEvent('gift_card_breakage_batch', $batch->id, 'posted', $this->payload($batch));

        return $batch->load($this->detailRelations());
    }

    /**
     * Build the accounting-bridge payload for a posted or reversal batch.
     *
     * @return array{
     *     branch_id:int|null,
     *     idempotency_key:string,
     *     source_version:int,
     *     amount:string,
     *     currency:string,
     *     currency_rate:string,
     *     currency_rate_direction:string,
     *     economic_date:string|null,
     *     lines:array<int, mixed>
     * }
     */
    private function payload(GiftCardBreakageBatch $batch): array
    {
        return [
            'branch_id' => $batch->branch_id,
            'idempotency_key' => "gift_card_breakage_batch:$batch->id:posted:$batch->idempotency_key",
            'source_version' => 1,
            'amount' => $batch->getRawOriginal('total_amount'),
            'currency' => $batch->currency,
            'currency_rate' => $batch->currency_rate,
            'currency_rate_direction' => 'source_to_default',
            'economic_date' => $batch->economic_date?->toDateString(),
            'lines' => $batch->lines->map(fn ($line) => $line->source_snapshot)->all(),
        ];
    }

    /**
     * Create the append-only reversal batch for a posted breakage batch.
     *
     * Only one reversal may exist. Replays must use the same idempotency key as
     * the existing reversal batch.
     *
     * @param  array{economic_date:string, idempotency_key:string, reason:string}  $data
     *
     * @throws Throwable
     */
    public function reverse(int $id, array $data): GiftCardBreakageBatch
    {
        $reversal = DB::transaction(function () use ($id, $data): GiftCardBreakageBatch {
            $original = GiftCardBreakageBatch::query()->with('lines')->lockForUpdate()->findOrFail($id);
            $existing = GiftCardBreakageBatch::query()->where('reversal_of_id', $original->id)->first();
            if ($existing) {
                abort_unless(
                    $existing->idempotency_key === $data['idempotency_key'],
                    422,
                    __('giftcard::messages.idempotency_key_conflict'),
                );

                return $existing->load($this->detailRelations());
            }
            abort_unless($original->status === GiftCardBreakageBatchStatus::Posted, 422, __('giftcard::messages.breakage_not_posted'));
            $reversal = GiftCardBreakageBatch::query()->create([
                'branch_id' => $original->branch_id,
                'reference_no' => $original->reference_no.'-REV',
                'status' => GiftCardBreakageBatchStatus::Posted,
                'currency' => $original->currency,
                'currency_rate' => $original->currency_rate,
                'total_amount' => $original->getRawOriginal('total_amount'),
                'economic_date' => $data['economic_date'],
                'idempotency_key' => $data['idempotency_key'],
                'review_note' => $data['reason'],
                'source_snapshot' => ['source_version' => 1, 'original_batch_id' => $original->id],
                'reviewed_by' => auth()->id(),
                'reviewed_at' => now(),
                'posted_by' => auth()->id(),
                'posted_at' => now(),
                'reversal_of_id' => $original->id,
            ]);
            foreach ($original->lines->sortBy('gift_card_id') as $line) {
                $transaction = $this->transactions->reverseBreakageTransaction($line->gift_card_transaction_id, [
                    'idempotency_key' => "gift_card_breakage:$original->id:card:$line->gift_card_id:reversal",
                    'economic_date' => $data['economic_date'],
                    'reason' => $data['reason'],
                ]);
                $reversal->lines()->create([
                    'gift_card_id' => $line->gift_card_id,
                    'gift_card_transaction_id' => $transaction->id,
                    'amount' => $line->getRawOriginal('amount'),
                    'currency' => $line->currency,
                    'currency_rate' => $line->currency_rate,
                    'source_snapshot' => ['original_line_id' => $line->id],
                ]);
            }
            $original->update(['status' => GiftCardBreakageBatchStatus::Reversed]);

            return $reversal->load('lines');
        });

        $this->postingBridge->recordEvent('gift_card_breakage_batch', $id, 'reversed', [
            ...$this->payload($reversal),
            'idempotency_key' => "gift_card_breakage_batch:$id:reversed:$reversal->idempotency_key",
            'original_event' => 'posted',
            'reason' => $reversal->review_note,
        ]);

        return $reversal->load($this->detailRelations());
    }
}
