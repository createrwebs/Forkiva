<?php

namespace Modules\DailyFinance\Services\PaymentSettlement;

use App\Forkiva;
use Brick\Math\BigDecimal;
use Brick\Math\Exception\MathException;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use JsonException;
use Modules\Branch\Models\Branch;
use Modules\Branch\Services\BranchAccess\BranchAccessServiceInterface;
use Modules\Core\Contracts\FinancialAccountLookupInterface;
use Modules\Currency\Currency;
use Modules\DailyFinance\Enums\PaymentSettlementStatus;
use Modules\DailyFinance\Models\PaymentSettlement;
use Modules\DailyFinance\Models\PaymentSettlementItem;
use Modules\DailyFinance\Models\PaymentSettlementReceipt;
use Modules\Payment\Enums\PaymentMethod;
use Modules\Payment\Enums\PaymentType;
use Modules\Payment\Models\Payment;

class PaymentSettlementService implements PaymentSettlementServiceInterface
{
    public function __construct(
        protected BranchAccessServiceInterface $branchAccess,
        protected FinancialAccountLookupInterface $accounts,
    ) {}

    /** {@inheritDoc} */
    public function meta(int $branchId): array
    {
        $branch = $this->branch($branchId);
        $linked = $this->accounts->available() && auth()->user()->can('admin.accounting_financial_accounts.index');
        $accounts = $linked
            ? array_values(array_filter(
                $this->accounts->list($branch->id, $branch->currency),
                fn (array $account) => ($account['type']['id'] ?? $account['type']) === 'bank'
                    && $this->accounts->resolve($account['key'], $branch->id, $branch->currency) !== null
            ))
            : [];

        return [
            'branch_id' => $branch->id,
            'currency' => $branch->currency,
            'precision' => Currency::subunit($branch->currency),
            'today' => now($branch->timezone ?: config('app.timezone'))->toDateString(),
            'methods' => array_map(fn (PaymentMethod $method) => $method->toTrans(), self::methods()),
            'accounts' => array_map(fn (array $account) => [
                'key' => $account['key'],
                'name' => $account['label'] ?? $account['name'],
            ], $accounts),
            'linked_accounts_available' => $linked,
        ];
    }

    /** {@inheritDoc} */
    public function summary(int $branchId): array
    {
        $branch = $this->branch($branchId);
        $summary = ['branch_id' => $branch->id, 'open_count' => 0, 'pending_count' => 0, 'partial_count' => 0];
        $balances = [];

        // Read decimal evidence in bounded batches; neither pagination nor float sums determine the total.
        PaymentSettlement::query()
            ->where('branch_id', $branch->id)
            ->whereIn('status', [PaymentSettlementStatus::Pending, PaymentSettlementStatus::Partial])
            ->select(['id', 'currency', 'status', 'expected_amount'])
            ->with('receipts:id,payment_settlement_id,amount')
            ->chunkById(200, function (Collection $records) use (&$summary, &$balances): void {
                foreach ($records as $record) {
                    $received = BigDecimal::zero();
                    foreach ($record->receipts as $receipt) {
                        $received = $received->plus($receipt->amount);
                    }
                    $remaining = BigDecimal::max(BigDecimal::of($record->expected_amount)->minus($received), 0);
                    $summary['open_count']++;
                    $summary[$record->status === PaymentSettlementStatus::Pending ? 'pending_count' : 'partial_count']++;
                    $balances[$record->currency] ??= ['currency' => $record->currency, 'open_count' => 0, 'remaining' => '0'];
                    $balances[$record->currency]['open_count']++;
                    $balances[$record->currency]['remaining'] = (string) BigDecimal::of($balances[$record->currency]['remaining'])->plus($remaining);
                }
            });

        ksort($balances);

        return [...$summary, 'balances' => array_values($balances)];
    }

    /**
     * Enforce the authenticated user's branch scope while allowing inactive branch history.
     */
    protected function branch(int $id): Branch
    {
        return Branch::query()
            ->withoutGlobalActive()
            ->findOrFail($this->branchAccess->resolve($id, true));
    }

    /**
     * Electronic source methods retain the existing cross-client PaymentMethod contract.
     *
     * @return array<int, PaymentMethod>
     */
    protected static function methods(): array
    {
        return [PaymentMethod::Card, PaymentMethod::BankTransfer, PaymentMethod::MobileWallet];
    }

    /** {@inheritDoc} */
    public function candidates(array $filters): LengthAwarePaginator
    {
        $branch = $this->branch((int) $filters['branch_id']);
        $from = CarbonImmutable::parse(
            $filters['from'],
            $branch->timezone ?: config('app.timezone')
        )->startOfDay();
        $until = CarbonImmutable::parse(
            $filters['to'],
            $branch->timezone ?: config('app.timezone')
        )->addDay()->startOfDay();

        if ($from->diffInDays($until) > 366) {
            $this->invalid('to', 'date_range');
        }

        return $this->eligible($branch)
            ->with('branch:id,timezone')
            ->where('method', $filters['method'])
            ->whereRaw('COALESCE(received_at, created_at) >= ?', [$from->setTimezone(config('app.timezone'))])
            ->whereRaw('COALESCE(received_at, created_at) < ?', [$until->setTimezone(config('app.timezone'))])
            ->whereNotIn(
                'id',
                PaymentSettlementItem::query()
                    ->whereNotNull('active_payment_id')
                    ->select('active_payment_id')
            )
            ->when($filters['search'] ?? null, fn (Builder $query, string $search) => $query->search($search))
            ->orderByRaw('COALESCE(received_at, created_at) DESC')
            ->orderByDesc('id')
            ->paginate(Forkiva::paginate());
    }

    /**
     * Reject a settlement field using the module's translated validation message.
     *
     * @throws ValidationException
     */
    protected function invalid(string $field, string $key): never
    {
        throw ValidationException::withMessages([$field => __('dailyfinance::payment_settlements.errors.'.$key)]);
    }

    /**
     * Restrict sources to active, positive electronic collections in the branch currency.
     * Callers apply reservation exclusions and collection-date boundaries separately.
     *
     * @return Builder<Payment>
     */
    protected function eligible(Branch $branch): Builder
    {
        return Payment::query()
            ->where('branch_id', $branch->id)
            ->active()
            ->where('type', PaymentType::Payment)
            ->where('currency', $branch->currency)
            ->whereRaw('COALESCE(received_at, created_at) <= ?', [now()->toDateTimeString()])
            ->whereIn('method', self::methods())
            ->where('amount', '>', 0);
    }

    /** {@inheritDoc} */
    public function history(array $filters): LengthAwarePaginator
    {
        $branch = $this->branch((int) $filters['branch_id']);

        return PaymentSettlement::query()
            ->where('branch_id', $branch->id)
            ->with(['createdBy', 'receipts.createdBy'])
            ->withCount('items')
            ->when(
                $filters['status'] ?? null,
                fn (Builder $query, string $status) => $status === 'outstanding'
                    ? $query->whereIn('status', [PaymentSettlementStatus::Pending, PaymentSettlementStatus::Partial])
                    : $query->where('status', $status)
            )
            ->when(
                $filters['method'] ?? null,
                fn (Builder $query, string $method) => $query->where('method', $method)
            )
            ->latest('id')
            ->paginate(Forkiva::paginate());
    }

    /** {@inheritDoc} */
    public function show(int $branchId, int $id): PaymentSettlement
    {
        $branch = $this->branch($branchId);

        return $this->details(PaymentSettlement::query()->where('branch_id', $branch->id)->findOrFail($id));
    }

    /**
     * Include source snapshots, bank-credit evidence and authors required by settlement resources.
     */
    protected function details(PaymentSettlement $record): PaymentSettlement
    {
        return $record->load(['createdBy', 'cancelledBy', 'items', 'receipts.createdBy'])->loadCount('items');
    }

    /** {@inheritDoc} */
    public function receive(int $branchId, int $id, array $data): PaymentSettlement
    {
        $branch = $this->branch($branchId);

        return DB::transaction(function () use ($branch, $id, $data): PaymentSettlement {
            $record = $this->lockedRecord($branch->id, $id);
            $amount = $this->amount($data['amount'], $record->currency, 'amount');

            if ($amount->isZero()) {
                $this->invalid('amount', 'amount');
            }

            $key = Str::lower($data['request_key']);
            $reference = Str::upper(trim($data['reference']));
            $reason = trim($data['variance_reason'] ?? '') ?: null;
            $hash = $this->fingerprint([
                (string) $amount,
                $data['received_date'],
                $reference,
                (bool) ($data['is_final'] ?? false),
                $reason,
                auth()->id(),
            ]);
            $existing = $record->receipts()->where('request_key', $key)->first();

            if ($existing) {
                abort_unless(
                    hash_equals($existing->request_hash, $hash),
                    409,
                    __('dailyfinance::payment_settlements.errors.replay')
                );

                return $this->details($record);
            }

            abort_unless(
                in_array($record->status, [PaymentSettlementStatus::Pending, PaymentSettlementStatus::Partial], true),
                409,
                __('dailyfinance::payment_settlements.errors.terminal')
            );

            $firstSource = CarbonImmutable::parse($record->items()->min('collected_at'))
                ->setTimezone($record->timezone)
                ->toDateString();

            if (
                $data['received_date'] < $firstSource
                || $data['received_date'] > now($record->timezone)->toDateString()
            ) {
                $this->invalid('received_date', 'receipt_date');
            }

            if (
                PaymentSettlementReceipt::query()
                    ->where('branch_id', $record->branch_id)
                    ->where('account_fingerprint', $record->account_fingerprint)
                    ->where('reference', $reference)
                    ->exists()
            ) {
                $this->invalid('reference', 'bank_reference');
            }

            $actual = $amount;

            foreach ($record->receipts()->lockForUpdate()->get(['amount']) as $receipt) {
                $actual = $actual->plus($receipt->amount);
            }

            if ($actual->isGreaterThan('99999999999999.9999')) {
                $this->invalid('amount', 'amount');
            }

            $expected = BigDecimal::of($record->expected_amount);
            $final = ($data['is_final'] ?? false) || $actual->isGreaterThanOrEqualTo($expected);

            if ($final && ! $actual->isEqualTo($expected) && mb_strlen($reason ?? '') < 3) {
                $this->invalid('variance_reason', 'variance_reason');
            }

            $record->receipts()->create([
                'branch_id' => $record->branch_id,
                'account_fingerprint' => $record->account_fingerprint,
                'amount' => (string) $amount,
                'received_date' => $data['received_date'],
                'reference' => $reference,
                'is_final' => $final,
                'variance_reason' => $reason,
                'request_key' => $key,
                'request_hash' => $hash,
            ]);

            $record->update([
                'status' => $final ? PaymentSettlementStatus::Settled : PaymentSettlementStatus::Partial,
            ]);

            return $this->details($record);
        });
    }

    /**
     * Lock the branch before its settlement to preserve the shared mutation lock order.
     * Call within a transaction after resolving branch access.
     */
    protected function lockedRecord(int $branchId, int $id): PaymentSettlement
    {
        Branch::query()->withoutGlobalActive()->whereKey($branchId)->lockForUpdate()->firstOrFail();

        return PaymentSettlement::query()->where('branch_id', $branchId)->lockForUpdate()->findOrFail($id);
    }

    /**
     * User-entered amounts must fit the currency subunit exactly; source precision is never silently rounded.
     *
     * @throws ValidationException When the amount exceeds its precision or permitted range.
     */
    protected function amount(string $value, string $currency, string $field): BigDecimal
    {
        try {
            $amount = BigDecimal::of($value)->toScale(Currency::subunit($currency));
        } catch (MathException) {
            $this->invalid($field, 'precision');
        }

        if ($amount->isNegative() || $amount->isGreaterThan('99999999999999.9999')) {
            $this->invalid($field, 'amount');
        }

        return $amount;
    }

    /**
     * Hash caller-normalized values for request replay checks and bank-destination identity.
     * Element order and decimal-string representations are significant to the fingerprint.
     *
     * @param  list<mixed>  $data
     *
     * @throws JsonException When the normalized values cannot be encoded.
     */
    protected function fingerprint(array $data): string
    {
        return hash('sha256', json_encode($data, JSON_THROW_ON_ERROR));
    }

    /** {@inheritDoc} */
    public function create(array $data): PaymentSettlement
    {
        $branch = $this->branch((int) $data['branch_id']);

        return DB::transaction(function () use ($branch, $data): PaymentSettlement {
            // All batch actions lock the branch first; collection reservations then lock payments by ID.
            $branch = Branch::query()
                ->withoutGlobalActive()
                ->whereKey($branch->id)
                ->lockForUpdate()
                ->firstOrFail();

            $key = Str::lower($data['request_key']);
            $existing = PaymentSettlement::query()
                ->where('branch_id', $branch->id)
                ->where('request_key', $key)
                ->first();

            $currency = $existing?->currency ?? $branch->currency;
            $fees = $this->amount($data['fees'], $currency, 'fees');
            $ids = array_map('intval', $data['payment_ids']);
            sort($ids);
            $sources = array_map(fn (int $id) => [$id, $data['source_hashes'][$id] ?? null], $ids);
            $accountKey = $data['account_key'] ?? null;
            $destination = $accountKey ? null : trim($data['destination_name'] ?? '');
            $notes = trim($data['notes'] ?? '') ?: null;
            $reference = trim($data['reference']);
            $hash = $this->fingerprint([
                $sources,
                $data['method'],
                (string) $fees,
                $accountKey,
                $destination,
                $reference,
                $notes,
                auth()->id(),
            ]);

            if ($existing) {
                abort_unless(
                    hash_equals($existing->request_hash, $hash),
                    409,
                    __('dailyfinance::payment_settlements.errors.replay')
                );

                return $this->details($existing);
            }

            if ($accountKey) {
                abort_unless(auth()->user()->can('admin.accounting_financial_accounts.index'), 403);
                $account = $this->accounts->resolve($accountKey, $branch->id, $currency);

                if (! $account || $account['type'] !== 'bank') {
                    $this->invalid('account_key', 'account');
                }

                $destination = $account['name'];
            } elseif ($destination === '') {
                $this->invalid('destination_name', 'destination');
            }

            $payments = $this->eligible($branch)
                ->whereIn('id', $ids)
                ->orderBy('id')
                ->lockForUpdate()
                ->get();

            if (
                $payments->count() !== count($ids)
                || $payments->contains(fn (Payment $payment) => $payment->method->value !== $data['method'])
            ) {
                $this->invalid('payment_ids', 'sources');
            }

            if (
                PaymentSettlementItem::query()
                    ->whereIn('active_payment_id', $ids)
                    ->lockForUpdate()
                    ->exists()
            ) {
                $this->invalid('payment_ids', 'reserved');
            }

            $gross = BigDecimal::zero()->toScale(Currency::subunit($currency));

            /** @var Payment $payment */
            foreach ($payments as $payment) {
                if (
                    ! isset($data['source_hashes'][$payment->id])
                    || ! hash_equals(
                        PaymentSettlementSource::hash($payment),
                        $data['source_hashes'][$payment->id]
                    )
                ) {
                    abort(409, __('dailyfinance::payment_settlements.source_changed'));
                }

                $gross = $gross->plus(
                    $this->amount((string) $payment->getRawOriginal('amount'), $currency, 'payment_ids')
                );
            }

            if ($gross->isGreaterThan('99999999999999.9999')) {
                $this->invalid('payment_ids', 'amount');
            }

            if ($fees->isGreaterThanOrEqualTo($gross)) {
                $this->invalid('fees', 'fees');
            }

            $record = PaymentSettlement::create([
                'branch_id' => $branch->id,
                'method' => $data['method'],
                'currency' => $currency,
                'timezone' => $branch->timezone ?: config('app.timezone'),
                'status' => PaymentSettlementStatus::Pending,
                'reference' => $reference,
                'account_key' => $accountKey,
                'destination_name' => $destination,
                'account_fingerprint' => $this->fingerprint([$accountKey ?? Str::lower($destination), $currency]),
                'gross_amount' => (string) $gross,
                'fees' => (string) $fees,
                'expected_amount' => (string) $gross->minus($fees),
                'request_key' => $key,
                'request_hash' => $hash,
                'notes' => $notes,
            ]);

            /** @var Payment $payment */
            foreach ($payments as $payment) {
                $record->items()->create([
                    'payment_id' => $payment->id,
                    'active_payment_id' => $payment->id,
                    'amount' => (string) $this->amount(
                        (string) $payment->getRawOriginal('amount'),
                        $currency,
                        'payment_ids'
                    ),
                    'order_reference_no' => $payment->order_reference_no,
                    'collected_at' => $payment->received_at ?? $payment->created_at,
                ]);
            }

            return $this->details($record);
        });
    }

    /** {@inheritDoc} */
    public function cancel(int $branchId, int $id, string $reason): PaymentSettlement
    {
        $branch = $this->branch($branchId);
        $reason = trim($reason);

        if (mb_strlen($reason) < 3) {
            $this->invalid('cancellation_reason', 'cancellation_reason');
        }

        return DB::transaction(function () use ($branch, $id, $reason): PaymentSettlement {
            $record = $this->lockedRecord($branch->id, $id);

            if ($record->status === PaymentSettlementStatus::Cancelled) {
                abort_unless(
                    $record->cancelled_by === auth()->id() && $record->cancellation_reason === $reason,
                    409
                );

                return $this->details($record);
            }

            abort_unless(
                $record->status === PaymentSettlementStatus::Pending && ! $record->receipts()->exists(),
                409,
                __('dailyfinance::payment_settlements.errors.terminal')
            );

            $record->update([
                'status' => PaymentSettlementStatus::Cancelled,
                'cancelled_by' => auth()->id(),
                'cancelled_at' => now(),
                'cancellation_reason' => $reason,
            ]);
            $record->items()->update(['active_payment_id' => null]);

            return $this->details($record);
        });
    }
}
