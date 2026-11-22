<?php

namespace Modules\GiftCard\Services\GiftCardTransaction;

use App\Forkiva;
use Brick\Math\BigDecimal;
use Brick\Math\Exception\NumberFormatException;
use Brick\Math\RoundingMode;
use Carbon\Carbon;
use DB;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use JsonException;
use Modules\Core\Contracts\FinancialAccountLookupInterface;
use Modules\Core\Contracts\FinancialPostingBridgeInterface;
use Modules\Currency\Currency;
use Modules\GiftCard\Enums\GiftCardFundingType;
use Modules\GiftCard\Enums\GiftCardStatus;
use Modules\GiftCard\Enums\GiftCardTransactionType;
use Modules\GiftCard\Events\GiftCardAdjusted;
use Modules\GiftCard\Events\GiftCardExpired;
use Modules\GiftCard\Events\GiftCardRecharged;
use Modules\GiftCard\Events\GiftCardRedeemed;
use Modules\GiftCard\Exceptions\InsufficientGiftCardBalanceException;
use Modules\GiftCard\Exceptions\InvalidGiftCardStateException;
use Modules\GiftCard\Models\GiftCard;
use Modules\GiftCard\Models\GiftCardTransaction;
use Modules\GiftCard\Support\GiftCardCurrencyRate;
use Modules\Payment\Models\Payment;
use Modules\Support\DateRange;
use Modules\Support\GlobalStructureFilters;
use Throwable;

class GiftCardTransactionService implements GiftCardTransactionServiceInterface
{
    public function __construct(
        protected FinancialAccountLookupInterface $financialAccounts,
        protected FinancialPostingBridgeInterface $postingBridge,
    ) {}

    /** {@inheritDoc} */
    public function label(): string
    {
        return __('giftcard::gift_card_transactions.gift_card_transaction');
    }

    /** {@inheritDoc} */
    public function get(array $filters = [], ?array $sorts = []): LengthAwarePaginator
    {
        return $this->exportQuery($filters, $sorts)
            ->paginate(Forkiva::paginate())
            ->withQueryString();
    }

    /** {@inheritDoc} */
    public function exportQuery(array $filters = [], ?array $sorts = [], array $context = []): Builder
    {
        return $this->baseQuery()
            ->filters($filters)
            ->sortBy($sorts);
    }

    /**
     * Build the base transaction query with standard relations and card visibility rules.
     */
    protected function baseQuery(): Builder
    {
        return GiftCardTransaction::query()
            ->with([
                'giftCard:id,code,uuid,liability_branch_id',
                'giftCard.liabilityBranch:id,name',
                'branch:id,name',
                'createdBy:id,name',
                'order:id,reference_no,currency',
                'payment:id,transaction_id,type,method,amount,currency',
                'originalTransaction:id,uuid,type',
                'reversedByTransaction:id,uuid,type',
            ])
            ->whereHas('giftCard');
    }

    /** {@inheritDoc} */
    public function exportResourceKey(): string
    {
        return 'gift_card_transactions';
    }

    /** {@inheritDoc} */
    public function exportPermission(): string
    {
        return 'admin.gift_card_transactions.export';
    }

    /** {@inheritDoc} */
    public function exportModelClass(): string
    {
        return GiftCardTransaction::class;
    }

    /** {@inheritDoc} */
    public function getModel(): GiftCardTransaction
    {
        return new ($this->model());
    }

    /** {@inheritDoc} */
    public function model(): string
    {
        return GiftCardTransaction::class;
    }

    /** {@inheritDoc} */
    public function show(int|string $id): GiftCardTransaction
    {
        return $this->findOrFail($id);
    }

    /** {@inheritDoc} */
    public function findOrFail(int|string $id): GiftCardTransaction
    {
        return $this->byIdentifier($this->baseQuery(), $id)->firstOrFail();
    }

    /**
     * Restrict a model query by its numeric primary key or UUID.
     *
     * Numeric identifiers must never be compared with the UUID column because
     * MySQL may coerce UUID text to a number and return an unrelated row.
     *
     * @template TModel of Model
     *
     * @param  Builder<TModel>  $query
     * @return Builder<TModel>
     */
    private function byIdentifier(Builder $query, int|string $id): Builder
    {
        $numericId = filter_var($id, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
        if ($numericId !== false) {
            return $query->whereKey($numericId);
        }

        return Str::isUuid((string) $id) ? $query->where('uuid', $id) : $query->whereRaw('1 = 0');
    }

    /** {@inheritDoc} */
    public function getStructureFilters(): array
    {
        $branchFilter = GlobalStructureFilters::branch();

        return [
            ...(is_null($branchFilter) ? [] : [$branchFilter]),
            [
                'key' => 'currency',
                'label' => __('giftcard::attributes.gift_card_transactions.currency'),
                'type' => 'select',
                'options' => Currency::supportedList(),
            ],
            [
                'key' => 'order_currency',
                'label' => __('giftcard::attributes.gift_card_transactions.order_currency'),
                'type' => 'select',
                'options' => Currency::supportedList(),
            ],
            [
                'key' => 'type',
                'label' => __('giftcard::gift_card_transactions.filters.type'),
                'type' => 'select',
                'options' => GiftCardTransactionType::toArrayTrans(),
            ],
            GlobalStructureFilters::from(),
            GlobalStructureFilters::to(),
        ];
    }

    /** {@inheritDoc} */
    public function expireEligibleCards(): int
    {
        $count = 0;

        GiftCard::query()
            ->whereIn('status', [GiftCardStatus::Active->value, GiftCardStatus::Used->value])
            ->whereNotNull('expiry_date')
            ->where('expiry_date', '<', DateRange::start(Date::today()))
            ->chunkById(100, function ($cards) use (&$count) {
                foreach ($cards as $card) {
                    if (BigDecimal::of((string) $card->getRawOriginal('current_balance'))->isGreaterThan(BigDecimal::zero())) {
                        $this->record($card, [
                            'type' => GiftCardTransactionType::Expire,
                            'amount' => $card->getRawOriginal('current_balance'),
                            'branch_id' => $card->branch_id,
                            'notes' => __('giftcard::messages.card_expired_automatically'),
                        ]);
                    } else {
                        $card->update(['status' => GiftCardStatus::Expired]);
                        event(new GiftCardExpired(
                            giftCard: $card->fresh(['branch', 'customer.roles', 'batch']) ?? $card,
                        ));
                    }

                    $count++;
                }
            });

        return $count;
    }

    /** {@inheritDoc} */
    public function record(GiftCard $giftCard, array $data): GiftCardTransaction
    {
        $type = $data['type'] instanceof GiftCardTransactionType
            ? $data['type']
            : GiftCardTransactionType::from($data['type']);

        $idempotencyKey = $data['idempotency_key'] ?? $this->defaultIdempotencyKey($giftCard, $type, $data);
        $existing = GiftCardTransaction::query()->where('idempotency_key', $idempotencyKey)->first();
        if ($existing) {
            $this->assertIdempotencyMatches($existing, $giftCard, $type, $data);

            return $existing;
        }

        $transaction = DB::transaction(function () use ($giftCard, $data, $type, $idempotencyKey) {
            /** @var GiftCard $lockedGiftCard */
            $lockedGiftCard = GiftCard::query()
                ->whereKey($giftCard->id)
                ->lockForUpdate()
                ->firstOrFail();
            $existing = GiftCardTransaction::query()
                ->where('idempotency_key', $idempotencyKey)
                ->first();
            if ($existing) {
                $this->assertIdempotencyMatches($existing, $lockedGiftCard, $type, $data);

                return $existing;
            }

            if ($lockedGiftCard->isExpired() && ! in_array($type, [GiftCardTransactionType::Expire, GiftCardTransactionType::Breakage, GiftCardTransactionType::Reversal], true)) {
                throw new InvalidGiftCardStateException(__('giftcard::messages.card_expired'));
            }

            if ($lockedGiftCard->isDisabled() && ! in_array($type, [GiftCardTransactionType::Adjustment, GiftCardTransactionType::AdjustmentDecrease, GiftCardTransactionType::Expire, GiftCardTransactionType::Reversal], true)) {
                throw new InvalidGiftCardStateException(__('giftcard::messages.card_disabled'));
            }

            $original = ! empty($data['original_transaction_id'])
                ? GiftCardTransaction::query()->lockForUpdate()->findOrFail($data['original_transaction_id'])
                : null;

            if ($type === GiftCardTransactionType::IssueVoid) {
                abort_unless(
                    $original
                    && $original->gift_card_id === $lockedGiftCard->id
                    && $original->type === GiftCardTransactionType::Purchase
                    && is_null($original->reversed_at),
                    422,
                    __('giftcard::messages.issue_void_must_match_original_issue'),
                );
                abort_if(
                    GiftCardTransaction::query()
                        ->where('original_transaction_id', $original->id)
                        ->where('type', GiftCardTransactionType::IssueVoid->value)
                        ->whereNull('reversed_at')
                        ->exists(),
                    422,
                    __('giftcard::messages.issue_already_voided'),
                );

                // Preserve the exact funding and FX snapshots from the issuance.
                $data['amount'] = $original->getRawOriginal('amount');
                $data['currency_rate'] = $original->currency_rate;
                $data['funding_type'] = $original->funding_type;
                $data['tender_method'] = $original->tender_method;
                $data['financial_account_key'] = $original->financial_account_key;
                $data['financial_account_snapshot'] = $original->financial_account_snapshot;
            }
            $amount = $this->decimalOrValidationError($data['amount'] ?? null, 'amount', 4);
            $balanceBefore = BigDecimal::of((string) $lockedGiftCard->getRawOriginal('current_balance'))->toScale(4, RoundingMode::HalfUp);
            $direction = $type === GiftCardTransactionType::Reversal
                ? -($original?->type->affectsBalanceBy() ?? 0)
                : $type->affectsBalanceBy();
            $balanceAfter = match ($direction) {
                -1 => $balanceBefore->minus($amount),
                1 => $balanceBefore->plus($amount),
                default => $balanceBefore,
            };

            if ($balanceAfter->isNegative()) {
                throw new InsufficientGiftCardBalanceException(__('giftcard::messages.insufficient_balance'));
            }

            $currencyRate = $this->decimalOrValidationError(
                array_key_exists('currency_rate', $data)
                    ? $data['currency_rate']
                    : GiftCardCurrencyRate::toDefault($lockedGiftCard->currency),
                'currency_rate',
                8,
            );
            if ($currencyRate->isLessThanOrEqualTo(BigDecimal::zero())) {
                throw ValidationException::withMessages(['currency_rate' => [__('giftcard::messages.currency_rate_invalid')]]);
            }
            $fundingType = $data['funding_type'] ?? null;
            $financialAccount = $data['financial_account_snapshot'] ?? $this->resolveFinancialAccount(
                $lockedGiftCard,
                $fundingType,
                $data['financial_account_key'] ?? null,
                $type,
            );
            $paymentId = $data['payment_id'] ?? $this->resolveLinkedPaymentId($lockedGiftCard, $type, $data);
            $liabilityBranchId = $lockedGiftCard->liability_branch_id ?: $lockedGiftCard->branch_id;
            if ($this->financialAccounts->available()) {
                if (! $liabilityBranchId) {
                    throw ValidationException::withMessages(['branch_id' => [__('giftcard::messages.accounting_branch_required')]]);
                }
                $operationalBranchId = (int) ($data['branch_id'] ?? $liabilityBranchId);
                if (in_array($type, [GiftCardTransactionType::Redeem, GiftCardTransactionType::Refund], true)
                    && $operationalBranchId !== (int) $liabilityBranchId) {
                    throw ValidationException::withMessages(['branch_id' => [__('giftcard::messages.liability_branch_mismatch')]]);
                }
                if (! $lockedGiftCard->liability_branch_id) {
                    $lockedGiftCard->liability_branch_id = $liabilityBranchId;
                }
            }
            $economicDate = (string) ($data['economic_date'] ?? Carbon::parse($data['transaction_at'] ?? now())->toDateString());
            $fingerprint = $this->requestFingerprint(
                $lockedGiftCard,
                $type,
                $data,
                (string) $amount,
                (string) $currencyRate,
                $economicDate,
                $paymentId,
            );

            $lockedGiftCard->current_balance = (string) $balanceAfter;
            $lockedGiftCard->status = $this->resolveStatus($lockedGiftCard, $type, $balanceAfter);
            $lockedGiftCard->save();

            return $lockedGiftCard->transactions()->create([
                'type' => $type,
                'amount' => (string) $amount,
                'balance_before' => (string) $balanceBefore,
                'balance_after' => (string) $balanceAfter,
                'currency' => $lockedGiftCard->currency,
                'exchange_rate' => $data['exchange_rate'] ?? null,
                'currency_rate' => (string) $currencyRate,
                'currency_rate_source' => $data['currency_rate_source'] ?? ($original ? 'original_transaction' : 'operational_commit'),
                'funding_type' => $fundingType,
                'tender_method' => $data['tender_method'] ?? null,
                'financial_account_key' => $data['financial_account_key'] ?? null,
                'financial_account_snapshot' => $financialAccount,
                'amount_in_order_currency' => $data['amount_in_order_currency'] ?? null,
                'order_currency' => $data['order_currency'] ?? null,
                'order_id' => $data['order_id'] ?? null,
                'payment_id' => $paymentId,
                'original_transaction_id' => $data['original_transaction_id'] ?? null,
                'idempotency_key' => $idempotencyKey,
                'request_fingerprint' => $fingerprint,
                'branch_id' => $this->financialAccounts->available() ? $liabilityBranchId : ($data['branch_id'] ?? $lockedGiftCard->branch_id),
                'notes' => $data['notes'] ?? null,
                'transaction_at' => $data['transaction_at'] ?? now(),
                'economic_date' => $economicDate,
                'source_snapshot' => [
                    'source_version' => 1,
                    'gift_card_id' => $lockedGiftCard->id,
                    'gift_card_code' => $lockedGiftCard->code,
                    'type' => $type->value,
                    'amount' => (string) $amount,
                    'balance_before' => (string) $balanceBefore,
                    'balance_after' => (string) $balanceAfter,
                    'currency' => $lockedGiftCard->currency,
                    'currency_rate' => (string) $currencyRate,
                    'exchange_rate' => $data['exchange_rate'] ?? null,
                    'economic_date' => $economicDate,
                    'liability_branch_id' => $liabilityBranchId,
                    'request_fingerprint' => $fingerprint,
                    'funding_type' => $fundingType,
                    'payment_id' => $paymentId,
                    'order_id' => $data['order_id'] ?? null,
                    'financial_account' => $financialAccount,
                ],
            ]);
        });

        $transaction->loadMissing([
            'giftCard.branch',
            'giftCard.customer.roles',
            'giftCard.batch',
        ]);

        match ($type) {
            GiftCardTransactionType::Redeem => event(new GiftCardRedeemed($transaction)),
            GiftCardTransactionType::Recharge => event(new GiftCardRecharged($transaction)),
            GiftCardTransactionType::Adjustment => event(new GiftCardAdjusted($transaction)),
            GiftCardTransactionType::Expire => event(new GiftCardExpired($transaction->giftCard, $transaction)),
            default => null,
        };

        $this->recordAccountingEvent($transaction);

        return $transaction;
    }

    /**
     * Build a deterministic idempotency key when the caller does not provide one.
     *
     * Payment and order owned transactions use their owning record identifiers. Other
     * transaction types fall back to a hash of the request fields that define the action.
     *
     * @param  array<string, mixed>  $data
     *
     * @throws JsonException
     */
    protected function defaultIdempotencyKey(GiftCard $giftCard, GiftCardTransactionType $type, array $data): string
    {
        if (! empty($data['payment_id'])) {
            return "gift_card:$giftCard->id:$type->value:payment:{$data['payment_id']}";
        }
        if (! empty($data['order_id']) && in_array($type, [GiftCardTransactionType::Redeem, GiftCardTransactionType::Refund], true)) {
            return "gift_card:$giftCard->id:$type->value:order:{$data['order_id']}";
        }

        return "gift_card:$giftCard->id:$type->value:".hash('sha256', json_encode([
            $data['amount'] ?? null,
            $data['transaction_at'] ?? null,
            $data['notes'] ?? null,
        ], JSON_THROW_ON_ERROR));
    }

    /**
     * Ensure an existing idempotent transaction matches the incoming request.
     *
     * @param  array<string, mixed>  $data
     *
     * @throws ValidationException
     */
    protected function assertIdempotencyMatches(
        GiftCardTransaction $existing,
        GiftCard $giftCard,
        GiftCardTransactionType $type,
        array $data,
    ): void {
        $amount = (string) ($data['amount'] ?? $existing->getRawOriginal('amount'));
        $economicDate = (string) ($data['economic_date'] ?? $existing->economic_date?->toDateString());
        $paymentId = $data['payment_id'] ?? $existing->payment_id;
        $original = $type === GiftCardTransactionType::IssueVoid && ! empty($data['original_transaction_id'])
            ? GiftCardTransaction::query()->find($data['original_transaction_id'])
            : null;
        if ($original) {
            $amount = (string) $original->getRawOriginal('amount');
            $data['currency_rate'] = $original->currency_rate;
            $data['funding_type'] = $original->funding_type;
            $data['tender_method'] = $original->tender_method;
            $data['financial_account_key'] = $original->financial_account_key;
        }
        $resolved = [
            ...$data,
            'original_transaction_id' => $data['original_transaction_id'] ?? $existing->original_transaction_id,
            'funding_type' => $data['funding_type'] ?? $existing->funding_type,
            'tender_method' => $data['tender_method'] ?? $existing->tender_method,
            'financial_account_key' => $data['financial_account_key'] ?? $existing->financial_account_key,
            'order_id' => $data['order_id'] ?? $existing->order_id,
            'notes' => $data['notes'] ?? $existing->notes,
        ];
        $fingerprint = $this->requestFingerprint(
            $giftCard,
            $type,
            $resolved,
            (string) $this->decimalOrValidationError($amount, 'amount', 4),
            (string) $this->decimalOrValidationError($data['currency_rate'] ?? $existing->currency_rate, 'currency_rate', 8),
            $economicDate,
            $paymentId,
        );
        $matches = $existing->gift_card_id === $giftCard->id
            && $existing->type === $type
            && ($existing->request_fingerprint
                ? hash_equals($existing->request_fingerprint, $fingerprint)
                : $this->decimalOrValidationError($existing->getRawOriginal('amount'), 'amount', 4)
                    ->isEqualTo($this->decimalOrValidationError($amount, 'amount', 4)));
        abort_unless($matches, 422, __('giftcard::messages.idempotency_key_conflict'));
    }

    /**
     * Generate the immutable request fingerprint stored with a ledger row.
     *
     * The fingerprint intentionally contains only stable accounting inputs so a retry can be
     * verified without comparing unrelated request metadata.
     *
     * @param  array<string, mixed>  $data
     *
     * @throws JsonException
     */
    protected function requestFingerprint(
        GiftCard $giftCard,
        GiftCardTransactionType $type,
        array $data,
        string $amount,
        string $currencyRate,
        string $economicDate,
        ?int $paymentId,
    ): string {
        return hash('sha256', json_encode([
            'source_version' => 1,
            'gift_card_id' => $giftCard->id,
            'liability_branch_id' => $giftCard->liability_branch_id ?: $giftCard->branch_id,
            'type' => $type->value,
            'amount' => $amount,
            'currency' => $giftCard->currency,
            'currency_rate' => (string) $this->decimalOrValidationError($currencyRate, 'currency_rate', 8),
            'economic_date' => $economicDate,
            'original_transaction_id' => $data['original_transaction_id'] ?? null,
            'payment_id' => $paymentId,
            'order_id' => $data['order_id'] ?? null,
            'funding_type' => $data['funding_type'] ?? null,
            'tender_method' => $data['tender_method'] ?? null,
            'financial_account_key' => $data['financial_account_key'] ?? null,
            'notes' => $data['notes'] ?? null,
        ], JSON_THROW_ON_ERROR));
    }

    /**
     * Normalize a decimal input to the required scale or raise a Laravel validation error.
     *
     * @throws ValidationException
     */
    protected function decimalOrValidationError(mixed $value, string $field, int $scale): BigDecimal
    {
        $normalized = is_string($value) ? trim($value) : (is_int($value) || is_float($value) ? (string) $value : '');
        if ($normalized === '') {
            throw ValidationException::withMessages([
                $field => [__('validation.required', ['attribute' => __('giftcard::attributes.gift_card_transactions.'.$field)])],
            ]);
        }

        try {
            return BigDecimal::of($normalized)->toScale($scale, RoundingMode::HalfUp);
        } catch (NumberFormatException) {
            throw ValidationException::withMessages([
                $field => [__('validation.numeric', ['attribute' => __('giftcard::attributes.gift_card_transactions.'.$field)])],
            ]);
        }
    }

    /**
     * Resolve the financial account snapshot required for paid funding or cash-out actions.
     *
     * @return array<string, mixed>|null
     *
     * @throws ValidationException
     */
    protected function resolveFinancialAccount(
        GiftCard $giftCard,
        ?string $fundingType,
        ?string $key,
        GiftCardTransactionType $type,
    ): ?array {
        $requiresAccount = $fundingType === GiftCardFundingType::Paid->value
            || $type === GiftCardTransactionType::CashOut;
        if (! $this->financialAccounts->available() || ! $requiresAccount) {
            return null;
        }
        $liabilityBranchId = $giftCard->liability_branch_id ?: $giftCard->branch_id;
        if (! $liabilityBranchId) {
            throw ValidationException::withMessages(['branch_id' => [__('giftcard::messages.accounting_branch_required')]]);
        }
        $account = $this->financialAccounts->resolve((string) $key, (int) $liabilityBranchId, $giftCard->currency);
        if (! $account) {
            throw ValidationException::withMessages(['financial_account_key' => [__('giftcard::messages.financial_account_invalid')]]);
        }

        return $account;
    }

    /**
     * Locate the owning gift-card payment record for redemption or refund transactions.
     *
     * @param  array<string, mixed>  $data
     */
    protected function resolveLinkedPaymentId(GiftCard $giftCard, GiftCardTransactionType $type, array $data): ?int
    {
        if (! in_array($type, [GiftCardTransactionType::Redeem, GiftCardTransactionType::Refund], true) || empty($data['order_id'])) {
            return null;
        }

        return Payment::query()
            ->withOutGlobalBranchPermission()
            ->where('order_id', $data['order_id'])
            ->where('method', 'gift_card')
            ->where('meta->gift_card_id', $giftCard->id)
            ->when($type === GiftCardTransactionType::Refund, fn ($query) => $query->where('type', 'refund'))
            ->when($type === GiftCardTransactionType::Redeem, fn ($query) => $query->where('type', 'payment'))
            ->latest('id')
            ->value('id');
    }

    /**
     * Resolve the resulting gift card status after recording a transaction.
     */
    protected function resolveStatus(GiftCard $giftCard, GiftCardTransactionType $type, BigDecimal $balanceAfter): GiftCardStatus
    {
        if ($type === GiftCardTransactionType::Expire || $giftCard->isExpired()) {
            return GiftCardStatus::Expired;
        }

        if ($giftCard->status === GiftCardStatus::Disabled) {
            return GiftCardStatus::Disabled;
        }

        return $balanceAfter->isZero() ? GiftCardStatus::Used : GiftCardStatus::Active;
    }

    /**
     * Dispatch the accounting bridge event for transaction types owned by this service.
     *
     * Redemption, refund, expiry, reversal, and breakage accounting is handled by their
     * owning workflows, so this method intentionally skips them.
     *
     * @throws JsonException
     */
    protected function recordAccountingEvent(GiftCardTransaction $transaction): void
    {
        if (in_array($transaction->type, [GiftCardTransactionType::Redeem, GiftCardTransactionType::Refund, GiftCardTransactionType::Expire, GiftCardTransactionType::Reversal, GiftCardTransactionType::Breakage], true)) {
            return;
        }

        $dispatch = function () use ($transaction): void {
            $transactionType = match ($transaction->type) {
                GiftCardTransactionType::Purchase => $transaction->funding_type === GiftCardFundingType::Paid->value ? 'paid_issue' : 'promotional_issue',
                GiftCardTransactionType::Recharge => $transaction->funding_type === GiftCardFundingType::Paid->value ? 'paid_recharge' : 'promotional_recharge',
                GiftCardTransactionType::Adjustment => 'adjustment_increase',
                GiftCardTransactionType::AdjustmentDecrease => 'adjustment_decrease',
                default => $transaction->type->value,
            };
            $payload = [
                'branch_id' => $transaction->branch_id,
                'idempotency_key' => "gift_card_transaction:$transaction->id:posted",
                'source_version' => 1,
                'gift_card_id' => $transaction->gift_card_id,
                'transaction_type' => $transactionType,
                'amount' => $transaction->getRawOriginal('amount'),
                'currency' => $transaction->currency,
                'currency_rate' => $transaction->currency_rate,
                'currency_rate_direction' => 'source_to_default',
                'funding_type' => $transaction->funding_type,
                'tender_method' => $transaction->tender_method,
                'financial_account_key' => $transaction->financial_account_key,
                'financial_account_snapshot' => $transaction->financial_account_snapshot,
                'payment_id' => $transaction->payment_id,
                'order_id' => $transaction->order_id,
                'economic_date' => $transaction->economic_date?->toDateString(),
                'snapshot' => $transaction->source_snapshot,
            ];
            try {
                $this->postingBridge->recordEvent('gift_card_transaction', $transaction->id, 'posted', $payload);
            } catch (Throwable $exception) {
                $this->postingBridge->recordFailure('gift_card_transaction', $transaction->id, 'posted', class_basename($exception), $exception->getMessage(), $payload);
            }
        };

        DB::transactionLevel() > 0 ? DB::afterCommit($dispatch) : $dispatch();
    }

    /** {@inheritDoc} */
    public function recordFinancialAction(int|string $giftCardId, array $data): GiftCardTransaction
    {
        $giftCardQuery = GiftCard::query();
        $giftCard = $this->byIdentifier($giftCardQuery, $giftCardId)->firstOrFail();

        return $this->record($giftCard, $data);
    }

    /** {@inheritDoc} */
    public function reverse(int|string $id, array $data): GiftCardTransaction
    {
        return $this->reverseTransaction($id, $data);
    }

    /**
     * Reverse a gift card transaction while enforcing workflow ownership rules.
     *
     * @param  array<string, mixed>  $data
     * @param  'payment'|'breakage'|null  $owner
     *
     * @throws Throwable
     * @throws JsonException
     */
    protected function reverseTransaction(int|string $id, array $data, ?string $owner = null): GiftCardTransaction
    {
        $candidateQuery = GiftCardTransaction::query();
        $candidate = $this->byIdentifier($candidateQuery, $id)->firstOrFail();
        $reversal = DB::transaction(function () use ($candidate, $data, $owner): GiftCardTransaction {
            GiftCard::query()->whereKey($candidate->gift_card_id)->lockForUpdate()->firstOrFail();
            $original = GiftCardTransaction::query()->lockForUpdate()->findOrFail($candidate->id);
            if ($original->reversed_by_transaction_id) {
                $existing = GiftCardTransaction::query()->findOrFail($original->reversed_by_transaction_id);
                abort_unless(
                    $existing->idempotency_key === $data['idempotency_key'],
                    422,
                    __('giftcard::messages.idempotency_key_conflict'),
                );

                return $existing;
            }
            abort_if($original->type === GiftCardTransactionType::Expire, 422, __('giftcard::messages.transaction_expiry_not_reversible'));
            abort_if($original->type === GiftCardTransactionType::Purchase, 422, __('giftcard::messages.purchase_requires_issue_void'));
            abort_if(in_array($original->type, [GiftCardTransactionType::Redeem, GiftCardTransactionType::Refund], true) && $owner !== 'payment', 422, __('giftcard::messages.payment_owned_reversal'));
            abort_if($original->type === GiftCardTransactionType::Breakage && $owner !== 'breakage', 422, __('giftcard::messages.breakage_owned_reversal'));

            $reversal = $this->record($original->giftCard, [
                'type' => GiftCardTransactionType::Reversal,
                'amount' => $original->getRawOriginal('amount'),
                'branch_id' => $original->branch_id,
                'currency_rate' => $original->currency_rate,
                'currency_rate_source' => 'original_transaction',
                'funding_type' => $original->funding_type,
                'tender_method' => $original->tender_method,
                'financial_account_key' => $original->financial_account_key,
                'financial_account_snapshot' => $original->financial_account_snapshot,
                'original_transaction_id' => $original->id,
                'idempotency_key' => $data['idempotency_key'],
                'economic_date' => $data['economic_date'] ?? now()->toDateString(),
                'notes' => $data['reason'],
            ]);

            $original->forceFill(['reversed_by_transaction_id' => $reversal->id, 'reversed_at' => now()])->save();

            return $reversal;
        });

        $original = $reversal->originalTransaction;
        if ($original && ! in_array($original->type, [GiftCardTransactionType::Redeem, GiftCardTransactionType::Refund, GiftCardTransactionType::Breakage], true)) {
            $payload = [
                'branch_id' => $original->branch_id,
                'idempotency_key' => "gift_card_transaction:$original->id:reversed:{$reversal->idempotency_key}",
                'source_version' => 1,
                'original_event' => 'posted',
                'currency' => $original->currency,
                'currency_rate' => $original->currency_rate,
                'economic_date' => $reversal->economic_date?->toDateString(),
                'reason' => $reversal->notes,
                'reversal_transaction_id' => $reversal->id,
            ];
            $this->postingBridge->recordEvent('gift_card_transaction', $original->id, 'reversed', $payload);
        }

        return $reversal;
    }

    /** {@inheritDoc} */
    public function reversePaymentTransaction(int $paymentId, array $data): ?GiftCardTransaction
    {
        $transaction = GiftCardTransaction::query()->where('payment_id', $paymentId)->first();

        return $transaction ? $this->reverseTransaction($transaction->id, $data, 'payment') : null;
    }

    /** {@inheritDoc} */
    public function reverseBreakageTransaction(int|string $id, array $data): GiftCardTransaction
    {
        return $this->reverseTransaction($id, $data, 'breakage');
    }
}
