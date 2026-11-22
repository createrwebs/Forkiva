<?php

namespace Modules\Reservation\Services\ReservationDeposit;

use App\Forkiva;
use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use JsonException;
use Modules\Core\Contracts\FinancialAccountLookupInterface;
use Modules\Core\Contracts\FinancialPostingBridgeInterface;
use Modules\Currency\Currency;
use Modules\Order\Enums\OrderStatus;
use Modules\Order\Models\Order;
use Modules\Payment\Enums\PaymentMethod;
use Modules\Payment\Enums\PaymentType;
use Modules\Payment\Models\Payment;
use Modules\Reservation\Enums\ReservationDepositTransactionType;
use Modules\Reservation\Enums\ReservationPaymentStatus;
use Modules\Reservation\Enums\ReservationStatus;
use Modules\Reservation\Models\Reservation;
use Modules\Reservation\Models\ReservationDepositTransaction;
use Modules\Reservation\Services\ReservationSetting\ReservationSettingServiceInterface;
use Modules\Reservation\Support\ReservationDepositCurrencyRate;
use Throwable;

class ReservationDepositService implements ReservationDepositServiceInterface
{
    /**
     * Create the deposit ledger service with accounting and settings dependencies.
     */
    public function __construct(
        protected FinancialAccountLookupInterface $financialAccounts,
        protected FinancialPostingBridgeInterface $postingBridge,
        protected ReservationSettingServiceInterface $settings,
    ) {}

    /**
     * Find one deposit ledger transaction with display relations.
     */
    public function show(int|string $transactionId): ReservationDepositTransaction
    {
        return ReservationDepositTransaction::query()
            ->with($this->relations())
            ->findOrFail($transactionId);
    }

    /**
     * Get relations needed for ledger listing and detail responses.
     *
     * @return list<string>
     */
    private function relations(): array
    {
        return [
            'reservation:id,reservation_number',
            'order:id,reference_no',
            'invoice:id,invoice_number',
            'payment:id,transaction_id,voided_at',
            'payment.allocations.invoice:id,invoice_number',
            'originalTransaction:id,type',
            'reversedByTransaction:id,type',
            'createdBy:id,name',
        ];
    }

    /**
     * Get the export resource key for deposit ledger transactions.
     */
    public function exportResourceKey(): string
    {
        return 'reservation_deposit_transactions';
    }

    /**
     * Get the permission required to export deposit ledger transactions.
     */
    public function exportPermission(): string
    {
        return 'admin.reservation_deposit_transactions.export';
    }

    /**
     * Get the model class used by the export source.
     *
     * @return class-string<ReservationDepositTransaction>
     */
    public function exportModelClass(): string
    {
        return ReservationDepositTransaction::class;
    }

    /**
     * Get deposit action form metadata for a reservation.
     *
     * @return array<string, mixed>
     */
    public function getFormMeta(int|string $reservationId): array
    {
        $reservation = Reservation::query()
            ->where(fn ($query) => $query->whereKey($reservationId)->orWhere('reservation_number', $reservationId))
            ->firstOrFail();
        $setting = $this->settings->forBranch($reservation->branch_id);
        $orders = $this->eligibleOrders($reservation);

        return [
            'accounting_available' => $this->financialAccounts->available(),
            'financial_accounts' => $this->financialAccounts->list(
                $reservation->branch_id,
                $reservation->deposit_currency,
            ),
            'tender_methods' => collect(PaymentMethod::toArrayTrans())
                ->reject(fn (array $method) => in_array($method['id'], [
                    PaymentMethod::GiftCard->value,
                    PaymentMethod::ReservationDeposit->value,
                ], true))
                ->values()
                ->all(),
            'eligible_orders' => $orders,
            'deposit_refundable' => $setting->deposit_refundable,
        ];
    }

    /**
     * Search a bounded set of eligible orders by reference, number, or customer.
     *
     * @return list<array<string, mixed>>
     */
    public function searchEligibleOrders(int|string $reservationId, ?string $search = null, int $limit = 20): array
    {
        $reservation = Reservation::query()
            ->where(fn ($query) => $query->whereKey($reservationId)->orWhere('reservation_number', $reservationId))
            ->firstOrFail();

        return $this->eligibleOrders($reservation, $search, $limit);
    }

    /**
     * Build the bounded eligible-order result for deposit application.
     *
     * @return list<array<string, mixed>>
     */
    private function eligibleOrders(Reservation $reservation, ?string $search = null, int $limit = 20): array
    {
        $search = trim(mb_substr((string) $search, 0, 100));
        $searchPattern = "%$search%";
        $limit = max(1, min($limit, 50));

        return Order::query()
            ->withOutGlobalBranchPermission()
            ->with('customer:id,name')
            ->where('branch_id', $reservation->branch_id)
            ->where('currency', $reservation->deposit_currency)
            ->where('due_amount', '>', 0)
            ->whereNotIn('status', [
                OrderStatus::Cancelled,
                OrderStatus::Refunded,
                OrderStatus::Merged,
            ])
            ->where(fn (Builder $query) => $query
                ->whereNull('reservation_id')
                ->orWhere('reservation_id', $reservation->id))
            ->whereDoesntHave('invoices')
            ->when($search !== '', fn (Builder $query) => $query
                ->where(fn (Builder $query) => $query
                    ->where('reference_no', 'like', $searchPattern)
                    ->orWhere('order_number', 'like', $searchPattern)
                    ->orWhereHas('customer', fn (Builder $query) => $query
                        ->where('name', 'like', $searchPattern)
                        ->orWhere('phone', 'like', $searchPattern)
                        ->orWhere('email', 'like', $searchPattern))))
            ->orderByRaw(
                'CASE WHEN reservation_id = ? THEN 0 ELSE 1 END',
                [$reservation->id],
            )
            ->orderByDesc('created_at')
            ->limit($limit)
            ->get()
            ->map(function (Order $order) use ($reservation): array {
                return [
                    'id' => $order->id,
                    'key' => (string) $order->id,
                    'order_id' => $order->id,
                    'order_reference_no' => $order->reference_no,
                    'order_number' => $order->order_number,
                    'customer_id' => $order->customer_id,
                    'customer_name' => $order->getCustomerName(),
                    'reservation_linked' => (int) $order->reservation_id === $reservation->id,
                    'outstanding_amount' => (string) $order->getRawOriginal('due_amount'),
                    'currency' => $order->currency,
                ];
            })
            ->all();
    }

    /**
     * Get paginated reservation deposit ledger transactions.
     */
    public function get(array $filters = [], ?array $sorts = []): LengthAwarePaginator
    {
        return $this->exportQuery($filters, $sorts)
            ->paginate(Forkiva::paginate())
            ->withQueryString();
    }

    /**
     * Build the export/listing query for deposit ledger transactions.
     *
     * @param  array<string, mixed>  $filters
     * @param  array<int, array<string, mixed>>|null  $sorts
     * @param  array<string, mixed>  $context
     */
    public function exportQuery(array $filters = [], ?array $sorts = [], array $context = []): Builder
    {
        return ReservationDepositTransaction::query()
            ->with($this->relations())
            ->filters($filters)
            ->sortBy($sorts ?: [['key' => 'id', 'order' => 'desc']]);
    }

    /**
     * Record a collection, application, refund, or forfeiture in the append-only ledger.
     *
     * @param  array<string, mixed>  $data
     *
     * @throws Throwable
     */
    public function record(int|string $reservationId, array $data): ReservationDepositTransaction
    {
        $transaction = DB::transaction(function () use ($reservationId, $data): ReservationDepositTransaction {
            $reservation = Reservation::query()->where(fn ($query) => $query->whereKey($reservationId)->orWhere('reservation_number', $reservationId))->lockForUpdate()->firstOrFail();
            $type = $data['type'] instanceof ReservationDepositTransactionType
                ? $data['type']
                : ReservationDepositTransactionType::from($data['type']);
            abort_if(in_array($type, [ReservationDepositTransactionType::Opening, ReservationDepositTransactionType::Reversal], true), 422);
            $this->assertActionAllowed($reservation, $type);
            $amount = BigDecimal::of((string) $data['amount'])
                ->toScale(Currency::subunit($reservation->deposit_currency), RoundingMode::HalfUp);
            abort_if($amount->isLessThanOrEqualTo(BigDecimal::zero()), 422, __('reservation::messages.deposit_amount_invalid'));
            $fingerprint = $this->requestFingerprint($reservation, $type, $amount, $data);
            $existing = ReservationDepositTransaction::query()
                ->where('idempotency_key', $data['idempotency_key'])
                ->first();
            if ($existing) {
                $this->assertIdempotentMatch($existing, $reservation, $type, $amount, $data, $fingerprint);

                return $existing;
            }
            $balanceBefore = BigDecimal::of((string) $reservation->getRawOriginal('deposit_paid_amount'))->toScale(4, RoundingMode::HalfUp);
            $balanceAfter = $type->balanceDirection() > 0 ? $balanceBefore->plus($amount) : $balanceBefore->minus($amount);
            abort_if($balanceAfter->isLessThan(BigDecimal::zero()), 422, __('reservation::messages.deposit_balance_insufficient'));
            $latest = ReservationDepositTransaction::query()
                ->where('reservation_id', $reservation->id)
                ->orderByDesc('id')
                ->lockForUpdate()
                ->first();
            $baseBefore = BigDecimal::of((string) ($latest?->getRawOriginal('base_balance_after') ?? 0))
                ->toScale(8, RoundingMode::HalfUp);

            $order = $this->validateApplicationOrder($reservation, $type, $data);
            if ($type === ReservationDepositTransactionType::Application) {
                $orderOutstanding = BigDecimal::of((string) $order->getRawOriginal('due_amount'));
                if ($amount->isGreaterThan($orderOutstanding)) {
                    throw ValidationException::withMessages([
                        'amount' => [__('reservation::messages.deposit_application_exceeds_order_balance')],
                    ]);
                }
            }
            $financialAccount = $this->resolveFinancialAccount($reservation, $type, $data['financial_account_key'] ?? null);
            $rate = $type === ReservationDepositTransactionType::Collection
                ? BigDecimal::of(ReservationDepositCurrencyRate::toDefault($reservation->deposit_currency))
                : ($balanceBefore->isGreaterThan(BigDecimal::zero())
                    ? $baseBefore->dividedBy($balanceBefore, 8, RoundingMode::HalfUp)
                    : BigDecimal::of((string) $reservation->deposit_currency_rate)->toScale(8, RoundingMode::HalfUp));
            abort_if($rate->isLessThanOrEqualTo(BigDecimal::zero()), 422, __('reservation::messages.deposit_currency_rate_invalid'));
            $baseAmount = $amount->multipliedBy($rate)->toScale(8, RoundingMode::HalfUp);
            $baseAfter = $type->balanceDirection() > 0 ? $baseBefore->plus($baseAmount) : $baseBefore->minus($baseAmount);
            abort_if($baseAfter->isLessThan(BigDecimal::zero()), 422, __('reservation::messages.deposit_reversal_balance_invalid'));
            $effectiveRate = $balanceAfter->isGreaterThan(BigDecimal::zero())
                ? $baseAfter->dividedBy($balanceAfter, 8, RoundingMode::HalfUp)
                : $rate;

            $transaction = ReservationDepositTransaction::query()->create([
                'branch_id' => $reservation->branch_id,
                'reservation_id' => $reservation->id,
                'customer_id' => $order?->customer_id ?? $reservation->customer_id,
                'order_id' => $order?->id ?? $reservation->order_id,
                'invoice_id' => $type === ReservationDepositTransactionType::Application
                    ? null
                    : $reservation->invoice_id,
                'type' => $type,
                'amount' => (string) $amount,
                'base_amount' => (string) $baseAmount,
                'balance_before' => (string) $balanceBefore,
                'base_balance_before' => (string) $baseBefore,
                'balance_after' => (string) $balanceAfter,
                'base_balance_after' => (string) $baseAfter,
                'currency' => $reservation->deposit_currency,
                'currency_rate' => (string) $rate,
                'tender_method' => $data['tender_method'] ?? null,
                'financial_account_key' => $data['financial_account_key'] ?? null,
                'financial_account_snapshot' => $financialAccount,
                'economic_date' => $data['economic_date'],
                'idempotency_key' => $data['idempotency_key'],
                'request_fingerprint' => $fingerprint,
                'note' => $data['note'] ?? null,
                'source_snapshot' => [
                    'source_version' => 1,
                    'reservation_number' => $reservation->reservation_number,
                    'type' => $type->value,
                    'amount' => (string) $amount,
                    'currency' => $reservation->deposit_currency,
                    'currency_rate' => (string) $rate,
                    'currency_rate_direction' => 'source_to_default',
                    'balance_before' => (string) $balanceBefore,
                    'balance_after' => (string) $balanceAfter,
                    'base_amount' => (string) $baseAmount,
                    'base_balance_before' => (string) $baseBefore,
                    'base_balance_after' => (string) $baseAfter,
                    'order_id' => $order?->id,
                    'invoice_id' => null,
                    'financial_account' => $financialAccount,
                ],
                'created_by' => auth()->id(),
            ]);

            if ($type === ReservationDepositTransactionType::Application) {
                $payment = $order->storePayment([
                    'cashier_id' => auth()->id(),
                    'method' => PaymentMethod::ReservationDeposit->value,
                    'amount' => (string) $amount,
                    'currency_rate' => (string) $rate,
                    'transaction_id' => 'reservation-deposit-'.$transaction->id,
                    'received_at' => $data['economic_date'],
                    'skip_pos_cash_movement' => true,
                    'meta' => [
                        'sync_source' => 'reservation_deposit_application',
                        'reservation_id' => $reservation->id,
                        'reservation_deposit_transaction_id' => $transaction->id,
                    ],
                ]);
                $order->refreshDueAmount();
                $transaction->forceFill([
                    'payment_id' => $payment->id,
                    'source_snapshot' => [
                        ...$transaction->source_snapshot,
                        'payment_id' => $payment->id,
                    ],
                ])->save();
            }

            $reservation->forceFill([
                'order_id' => $reservation->order_id ?? $order?->id,
                'deposit_paid_amount' => (string) $balanceAfter,
                'deposit_currency_rate' => (string) $effectiveRate,
                'payment_status' => $this->paymentStatus($reservation, $type, $balanceAfter),
            ])->save();

            return $transaction;
        });

        $this->recordAccountingEvent($transaction, 'posted');

        return $transaction;
    }

    /**
     * Guard transaction types that depend on reservation status or deposit settings.
     */
    private function assertActionAllowed(Reservation $reservation, ReservationDepositTransactionType $type): void
    {
        if ($type === ReservationDepositTransactionType::Collection) {
            abort_unless(
                $reservation->deposit_required,
                422,
                __('reservation::messages.deposit_collection_not_required'),
            );
            abort_if($reservation->status->isTerminal(), 422, __('reservation::messages.deposit_collection_status_invalid'));
        }

        if ($type === ReservationDepositTransactionType::Forfeiture) {
            abort_unless(
                in_array($reservation->status, [
                    ReservationStatus::Cancelled,
                    ReservationStatus::NoShow,
                ], true),
                422,
                __('reservation::messages.deposit_forfeiture_status_invalid'),
            );
        }

        if ($type === ReservationDepositTransactionType::Refund) {
            abort_unless(
                $this->settings->forBranch($reservation->branch_id)->deposit_refundable,
                422,
                __('reservation::messages.deposit_refund_not_allowed'),
            );
        }
    }

    /**
     * Build the idempotency fingerprint for a new deposit ledger transaction.
     *
     * @param  array<string, mixed>  $data
     *
     * @throws JsonException
     */
    private function requestFingerprint(
        Reservation $reservation,
        ReservationDepositTransactionType $type,
        BigDecimal $amount,
        array $data,
    ): string {
        return hash('sha256', json_encode([
            'reservation_id' => $reservation->id,
            'type' => $type->value,
            'amount' => (string) $amount,
            'order_id' => $type === ReservationDepositTransactionType::Application
                ? ($data['order_id'] ?? $reservation->order_id)
                : null,
            'tender_method' => $data['tender_method'] ?? null,
            'financial_account_key' => $data['financial_account_key'] ?? null,
            'economic_date' => (string) $data['economic_date'],
            'note' => (string) ($data['note'] ?? ''),
        ], JSON_THROW_ON_ERROR));
    }

    /**
     * Ensure a repeated idempotency key matches the original transaction request exactly.
     *
     * @param  array<string, mixed>  $data
     */
    private function assertIdempotentMatch(
        ReservationDepositTransaction $existing,
        Reservation $reservation,
        ReservationDepositTransactionType $type,
        BigDecimal $amount,
        array $data,
        string $fingerprint,
    ): void {
        $matches = $existing->request_fingerprint
            ? hash_equals((string) $existing->request_fingerprint, $fingerprint)
            : (
                $existing->reservation_id === $reservation->id
                && $existing->type === $type
                && BigDecimal::of((string) $existing->getRawOriginal('amount'))->isEqualTo($amount)
                && $existing->tender_method === ($data['tender_method'] ?? null)
                && $existing->financial_account_key === ($data['financial_account_key'] ?? null)
                && $existing->economic_date?->toDateString() === (string) $data['economic_date']
                && $existing->note === ($data['note'] ?? null)
                && ($type !== ReservationDepositTransactionType::Application
                    || (int) $existing->order_id === (int) ($data['order_id'] ?? $reservation->order_id))
            );

        abort_unless($matches, 422, __('reservation::messages.deposit_idempotency_conflict'));
    }

    /**
     * Lock and validate the open order targeted by a deposit application.
     */
    private function validateApplicationOrder(
        Reservation $reservation,
        ReservationDepositTransactionType $type,
        array $data,
    ): ?Order {
        if ($type !== ReservationDepositTransactionType::Application) {
            return null;
        }

        $order = Order::query()->withOutGlobalBranchPermission()->lockForUpdate()->findOrFail($data['order_id'] ?? $reservation->order_id);
        abort_if(
            in_array($order->status, [
                OrderStatus::Cancelled,
                OrderStatus::Refunded,
                OrderStatus::Merged,
            ], true),
            422,
            __('reservation::messages.deposit_order_status_invalid'),
        );
        abort_if($order->getInvoice() !== null, 422, __('reservation::messages.deposit_order_already_invoiced'));
        abort_if($order->branch_id !== $reservation->branch_id, 422, __('reservation::messages.deposit_order_branch_mismatch'));
        abort_if($order->reservation_id !== null && (int) $order->reservation_id !== $reservation->id, 422, __('reservation::messages.deposit_order_linked_to_another_reservation'));
        abort_if(strtoupper($order->currency) !== strtoupper($reservation->deposit_currency), 422, __('reservation::messages.deposit_currency_mismatch'));
        abort_if(
            BigDecimal::of((string) $order->getRawOriginal('due_amount'))->isLessThanOrEqualTo(BigDecimal::zero()),
            422,
            __('reservation::messages.deposit_order_balance_invalid'),
        );
        if ($order->reservation_id === null) {
            $order->forceFill(['reservation_id' => $reservation->id])->save();
        }

        return $order;
    }

    /**
     * Resolve and snapshot the financial account required for cash-moving actions.
     *
     * @return array<string, mixed>|null
     */
    private function resolveFinancialAccount(Reservation $reservation, ReservationDepositTransactionType $type, ?string $key): ?array
    {
        if (! $this->financialAccounts->available() || ! in_array($type, [ReservationDepositTransactionType::Collection, ReservationDepositTransactionType::Refund], true)) {
            return null;
        }
        $account = $this->financialAccounts->resolve((string) $key, $reservation->branch_id, $reservation->deposit_currency);
        if (! $account) {
            throw ValidationException::withMessages(['financial_account_key' => [__('reservation::messages.deposit_financial_account_invalid')]]);
        }

        return $account;
    }

    /**
     * Derive the reservation payment status from the deposit requirement and current held balance.
     */
    private function paymentStatus(
        Reservation $reservation,
        ReservationDepositTransactionType $type,
        BigDecimal $balance,
        ?ReservationDepositTransactionType $reversedType = null,
    ): ReservationPaymentStatus {
        if (! $reservation->deposit_required) {
            return ReservationPaymentStatus::NotRequired;
        }
        if (in_array($type, [ReservationDepositTransactionType::Application, ReservationDepositTransactionType::Forfeiture], true)) {
            return ReservationPaymentStatus::Paid;
        }
        if (
            $type === ReservationDepositTransactionType::Reversal
            && in_array($reversedType, [
                ReservationDepositTransactionType::Application,
                ReservationDepositTransactionType::Refund,
                ReservationDepositTransactionType::Forfeiture,
            ], true)
        ) {
            return ReservationPaymentStatus::Paid;
        }
        if ($balance->isZero()) {
            return $type === ReservationDepositTransactionType::Refund ? ReservationPaymentStatus::Refunded : ReservationPaymentStatus::Unpaid;
        }
        $required = BigDecimal::of((string) $reservation->getRawOriginal('deposit_amount'));

        return $balance->isLessThan($required) ? ReservationPaymentStatus::PartiallyPaid : ReservationPaymentStatus::Paid;
    }

    /**
     * Dispatch a deposit accounting event after commit and record bridge failures.
     *
     * @param  array<string, mixed>  $extra
     *
     * @throws JsonException
     */
    private function recordAccountingEvent(?ReservationDepositTransaction $transaction, string $event, array $extra = []): void
    {
        if (! $transaction) {
            return;
        }
        $payload = [
            'branch_id' => $transaction->branch_id,
            'idempotency_key' => "reservation_deposit_transaction:$transaction->id:$event",
            'source_version' => 1,
            'reservation_id' => $transaction->reservation_id,
            'transaction_type' => $transaction->type->value,
            'amount' => $transaction->getRawOriginal('amount'),
            'currency' => $transaction->currency,
            'currency_rate' => $transaction->currency_rate,
            'currency_rate_direction' => 'source_to_default',
            'tender_method' => $transaction->tender_method,
            'financial_account_key' => $transaction->financial_account_key,
            'financial_account_snapshot' => $transaction->financial_account_snapshot,
            'order_id' => $transaction->order_id,
            'invoice_id' => $transaction->invoice_id,
            'economic_date' => $transaction->economic_date?->toDateString(),
            ...$extra,
        ];
        $dispatch = function () use ($transaction, $event, $payload): void {
            try {
                $this->postingBridge->recordEvent('reservation_deposit_transaction', $transaction->id, $event, $payload);
            } catch (Throwable $exception) {
                try {
                    $this->postingBridge->recordFailure('reservation_deposit_transaction', $transaction->id, $event, class_basename($exception), $exception->getMessage(), $payload);
                } catch (Throwable $diagnosticException) {
                    Log::error('Unable to record reservation deposit Accounting failure.', [
                        'reservation_deposit_transaction_id' => $transaction->id,
                        'idempotency_key' => $payload['idempotency_key'],
                        'posting_error' => $exception->getMessage(),
                        'diagnostic_error' => $diagnosticException->getMessage(),
                    ]);
                }
            }
        };

        DB::transactionLevel() > 0 ? DB::afterCommit($dispatch) : $dispatch();
    }

    /**
     * Reverse a deposit ledger transaction and apply the compensating balance effects.
     *
     * @param  array<string, mixed>  $data
     *
     * @throws Throwable
     */
    public function reverse(int|string $transactionId, array $data): ReservationDepositTransaction
    {
        $reversal = DB::transaction(function () use ($transactionId, $data): ReservationDepositTransaction {
            $original = ReservationDepositTransaction::query()->where(fn ($query) => $query->whereKey($transactionId)->orWhere('idempotency_key', $transactionId))->lockForUpdate()->firstOrFail();
            if ($original->reversed_by_transaction_id) {
                $existing = ReservationDepositTransaction::query()->findOrFail($original->reversed_by_transaction_id);
                abort_unless(
                    $existing->idempotency_key === $data['idempotency_key']
                    && hash_equals(
                        (string) $existing->request_fingerprint,
                        $this->reversalFingerprint($original, $data),
                    ),
                    422,
                    __('reservation::messages.deposit_idempotency_conflict'),
                );

                return $existing;
            }
            $existing = ReservationDepositTransaction::query()
                ->where('idempotency_key', $data['idempotency_key'])
                ->first();
            abort_if($existing !== null, 422, __('reservation::messages.deposit_idempotency_conflict'));
            abort_if($original->type === ReservationDepositTransactionType::Opening, 422, __('reservation::messages.deposit_opening_requires_remediation'));
            abort_if($original->type === ReservationDepositTransactionType::Reversal, 422, __('reservation::messages.deposit_reversal_cannot_be_reversed'));
            abort_if(
                $original->economic_date && $original->economic_date->isAfter($data['economic_date']),
                422,
                __('reservation::messages.deposit_reversal_date_invalid'),
            );
            $reservation = Reservation::query()->lockForUpdate()->findOrFail($original->reservation_id);
            $payment = $original->payment_id
                ? Payment::query()->withOutGlobalBranchPermission()->lockForUpdate()->findOrFail($original->payment_id)
                : null;
            $current = BigDecimal::of((string) $reservation->getRawOriginal('deposit_paid_amount'));
            $latest = ReservationDepositTransaction::query()
                ->where('reservation_id', $reservation->id)
                ->orderByDesc('id')
                ->lockForUpdate()
                ->first();
            $baseCurrent = BigDecimal::of((string) ($latest?->getRawOriginal('base_balance_after') ?? 0));
            $direction = -$original->type->balanceDirection();
            $amount = BigDecimal::of((string) $original->getRawOriginal('amount'));
            $baseAmount = BigDecimal::of((string) $original->getRawOriginal('base_amount'));
            $after = $direction > 0 ? $current->plus($amount) : $current->minus($amount);
            $baseAfter = $direction > 0 ? $baseCurrent->plus($baseAmount) : $baseCurrent->minus($baseAmount);
            abort_if($after->isLessThan(BigDecimal::zero()), 422, __('reservation::messages.deposit_reversal_balance_invalid'));
            abort_if($baseAfter->isLessThan(BigDecimal::zero()), 422, __('reservation::messages.deposit_reversal_balance_invalid'));
            $effectiveRate = $after->isGreaterThan(BigDecimal::zero())
                ? $baseAfter->dividedBy($after, 8, RoundingMode::HalfUp)
                : BigDecimal::of((string) $original->currency_rate)->toScale(8, RoundingMode::HalfUp);
            $fingerprint = $this->reversalFingerprint($original, $data);

            $reversal = ReservationDepositTransaction::query()->create([
                'branch_id' => $original->branch_id,
                'reservation_id' => $original->reservation_id,
                'customer_id' => $original->customer_id,
                'order_id' => $original->order_id,
                'invoice_id' => $original->invoice_id,
                'payment_id' => $original->payment_id,
                'type' => ReservationDepositTransactionType::Reversal,
                'amount' => (string) $amount,
                'base_amount' => (string) $baseAmount,
                'balance_before' => (string) $current,
                'base_balance_before' => (string) $baseCurrent,
                'balance_after' => (string) $after,
                'base_balance_after' => (string) $baseAfter,
                'currency' => $original->currency,
                'currency_rate' => $original->currency_rate,
                'tender_method' => $original->tender_method,
                'financial_account_key' => $original->financial_account_key,
                'financial_account_snapshot' => $original->financial_account_snapshot,
                'economic_date' => $data['economic_date'],
                'idempotency_key' => $data['idempotency_key'],
                'request_fingerprint' => $fingerprint,
                'original_transaction_id' => $original->id,
                'note' => $data['reason'],
                'source_snapshot' => [
                    'source_version' => 1,
                    'original_transaction_id' => $original->id,
                    'amount' => (string) $amount,
                    'base_amount' => (string) $baseAmount,
                    'currency' => $original->currency,
                    'currency_rate' => $original->currency_rate,
                    'currency_rate_direction' => 'source_to_default',
                    'balance_before' => (string) $current,
                    'balance_after' => (string) $after,
                    'base_balance_before' => (string) $baseCurrent,
                    'base_balance_after' => (string) $baseAfter,
                ],
                'created_by' => auth()->id(),
            ]);
            $original->forceFill(['reversed_by_transaction_id' => $reversal->id, 'reversed_at' => now()])->save();
            $reservation->forceFill([
                'deposit_paid_amount' => (string) $after,
                'deposit_currency_rate' => (string) $effectiveRate,
                'payment_status' => $this->paymentStatus(
                    $reservation,
                    ReservationDepositTransactionType::Reversal,
                    $after,
                    $original->type,
                ),
            ])->save();

            if ($payment && $original->type === ReservationDepositTransactionType::Application) {
                $order = Order::query()->withOutGlobalBranchPermission()->lockForUpdate()->findOrFail($original->order_id);
                $order->voidPayment($payment, $data['reason'], auth()->id());
                $order->refreshDueAmount();
                $this->refreshAllocatedInvoicePaymentTotals($payment);
            }

            return $reversal;
        });

        $this->recordAccountingEvent($reversal->originalTransaction, 'reversed', [
            'idempotency_key' => "reservation_deposit_transaction:{$reversal->original_transaction_id}:reversed:{$reversal->idempotency_key}",
            'economic_date' => $reversal->economic_date?->toDateString(),
            'reason' => $reversal->note,
            'original_event' => 'posted',
        ]);

        return $reversal;
    }

    /**
     * Refresh cached invoice payment totals when a later application reversal voids an allocated payment.
     */
    private function refreshAllocatedInvoicePaymentTotals(Payment $payment): void
    {
        $invoices = $payment->allocations()
            ->with('invoice')
            ->get()
            ->pluck('invoice')
            ->filter()
            ->unique('id');

        foreach ($invoices as $invoice) {
            $paid = $invoice->allocations()
                ->whereHas('payment', fn ($query) => $query
                    ->where('type', PaymentType::Payment)
                    ->whereNull('voided_at'))
                ->sum('amount');
            $refunded = $invoice->allocations()
                ->whereHas('payment', fn ($query) => $query
                    ->where('type', PaymentType::Refund)
                    ->whereNull('voided_at'))
                ->sum('amount');

            $invoice->forceFill([
                'paid_amount' => $paid,
                'refunded_amount' => $refunded,
                'net_paid' => BigDecimal::of((string) $paid)->minus((string) $refunded),
            ])->save();
        }
    }

    /**
     * Build the idempotency fingerprint for a deposit reversal request.
     *
     * @param  array<string, mixed>  $data
     *
     * @throws JsonException
     */
    private function reversalFingerprint(ReservationDepositTransaction $original, array $data): string
    {
        return hash('sha256', json_encode([
            'original_transaction_id' => $original->id,
            'economic_date' => (string) $data['economic_date'],
            'reason' => (string) $data['reason'],
        ], JSON_THROW_ON_ERROR));
    }
}
