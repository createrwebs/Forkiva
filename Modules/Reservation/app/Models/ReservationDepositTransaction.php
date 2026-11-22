<?php

namespace Modules\Reservation\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;
use Modules\Branch\Traits\HasBranch;
use Modules\Export\Contracts\ModelExportDefinitionInterface;
use Modules\Invoice\Models\Invoice;
use Modules\Order\Models\Order;
use Modules\Payment\Enums\PaymentMethod;
use Modules\Payment\Models\Payment;
use Modules\Reservation\Enums\ReservationDepositTransactionType;
use Modules\Support\Eloquent\Model;
use Modules\Support\Money;
use Modules\Support\Traits\HasCreatedBy;
use Modules\Support\Traits\HasFilters;
use Modules\Support\Traits\HasSortBy;
use Modules\User\Models\User;

/**
 * @property int $id
 * @property int $reservation_id
 * @property int|null $customer_id
 * @property int|null $order_id
 * @property int|null $invoice_id
 * @property int|null $payment_id
 * @property ReservationDepositTransactionType $type
 * @property Money $amount
 * @property Money $base_amount
 * @property Money $balance_before
 * @property Money $base_balance_before
 * @property Money $balance_after
 * @property Money $base_balance_after
 * @property string $currency
 * @property string $currency_rate Deposit-currency-to-default-currency rate.
 * @property PaymentMethod|null $tender_method
 * @property string|null $financial_account_key
 * @property array<string, mixed>|null $financial_account_snapshot
 * @property Carbon|null $economic_date
 * @property string $idempotency_key
 * @property string|null $request_fingerprint
 * @property int|null $original_transaction_id
 * @property int|null $reversed_by_transaction_id
 * @property Carbon|null $reversed_at
 * @property string|null $note
 * @property array<string, mixed>|null $source_snapshot
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Reservation $reservation
 * @property-read User|null $customer
 * @property-read Order|null $order
 * @property-read Invoice|null $invoice
 * @property-read Payment|null $payment
 * @property-read self|null $originalTransaction
 * @property-read self|null $reversedByTransaction
 *
 * @method static Builder|self search(string $value)
 */
class ReservationDepositTransaction extends Model implements ModelExportDefinitionInterface
{
    use HasBranch,
        HasCreatedBy,
        HasFilters,
        HasSortBy;

    /**
     * Default date column used by date filters and exports.
     */
    public static string $defaultDateColumn = 'economic_date';

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'branch_id',
        'reservation_id',
        'customer_id',
        'order_id',
        'invoice_id',
        'payment_id',
        'type',
        'amount',
        'base_amount',
        'balance_before',
        'base_balance_before',
        'balance_after',
        'base_balance_after',
        'currency',
        'currency_rate',
        'tender_method',
        'financial_account_key',
        'financial_account_snapshot',
        'economic_date',
        'idempotency_key',
        'request_fingerprint',
        'original_transaction_id',
        'reversed_by_transaction_id',
        'reversed_at',
        'note',
        'source_snapshot',
        'created_by',
    ];

    /**
     * Get exportable columns for reservation deposit transactions.
     *
     * @return list<array<string, mixed>>
     */
    public static function exportColumns(): array
    {
        return [
            ['path' => 'id', 'label' => 'admin::admin.table.id'],
            ['path' => 'reservation.reservation_number', 'label' => 'reservation::reservations.table.reservation_number', 'with' => ['reservation:id,reservation_number']],
            ['path' => 'type', 'label' => 'reservation::attributes.reservation_deposit_transactions.type'],
            ['path' => 'amount', 'label' => 'reservation::attributes.reservation_deposit_transactions.amount', 'type' => 'money'],
            ['path' => 'balance_before', 'label' => 'reservation::attributes.reservation_deposit_transactions.balance_before', 'type' => 'money'],
            ['path' => 'balance_after', 'label' => 'reservation::attributes.reservation_deposit_transactions.balance_after', 'type' => 'money'],
            ['path' => 'currency', 'label' => 'reservation::attributes.reservation_deposit_transactions.currency'],
            ['path' => 'currency_rate', 'label' => 'reservation::attributes.reservation_deposit_transactions.currency_rate'],
            ['path' => 'economic_date', 'label' => 'reservation::attributes.reservation_deposit_transactions.economic_date', 'type' => 'date'],
            ['path' => 'createdBy.name', 'label' => 'admin::admin.table.created_by', 'with' => ['createdBy:id,name']],
            ['path' => 'created_at', 'label' => 'admin::admin.table.created_at', 'type' => 'datetime'],
        ];
    }

    /**
     * Register immutability guards for append-only financial ledger facts.
     */
    protected static function booted(): void
    {
        static::updating(function (self $transaction): void {
            $mutable = [
                'payment_id',
                'source_snapshot',
                'reversed_by_transaction_id',
                'reversed_at',
                'updated_at',
            ];
            $financialChanges = array_diff(array_keys($transaction->getDirty()), $mutable);

            if ($financialChanges !== []) {
                throw new LogicException('Reservation deposit ledger transactions are immutable.');
            }
        });

        static::deleting(function (): never {
            throw new LogicException('Reservation deposit ledger transactions cannot be deleted.');
        });
    }

    /**
     * Get request filter keys supported by the deposit transaction listing.
     *
     * @return list<string>
     */
    public function allowedFilterKeys(): array
    {
        return ['search', 'from', 'to', 'branch_id', 'reservation_id', 'type', 'currency', 'created_by'];
    }

    /**
     * Scope a query to deposit transactions matching searchable text.
     *
     * @param  Builder<self>  $query
     */
    public function scopeSearch($query, string $value): void
    {
        $query->where(function ($query) use ($value): void {
            $query->like('idempotency_key', $value)
                ->orLike('note', $value)
                ->orWhereHas('reservation', fn ($query) => $query->like('reservation_number', $value));
        });
    }

    /**
     * Get the reservation that owns the deposit transaction.
     */
    public function reservation(): BelongsTo
    {
        return $this->belongsTo(Reservation::class)
            ->withOutGlobalBranchPermission()
            ->withTrashed();
    }

    /**
     * Get the customer associated with the transaction snapshot.
     */
    public function customer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'customer_id')
            ->withOutGlobalBranchPermission()
            ->withoutGlobalActive()
            ->withTrashed();
    }

    /**
     * Get the order affected by the deposit transaction.
     */
    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class)->withOutGlobalBranchPermission();
    }

    /**
     * Get the invoice affected by the deposit transaction.
     */
    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class)->withOutGlobalBranchPermission();
    }

    /**
     * Get the payment generated by a deposit application transaction.
     */
    public function payment(): BelongsTo
    {
        return $this->belongsTo(Payment::class)->withOutGlobalBranchPermission()->withTrashed();
    }

    /**
     * Get the original transaction reversed by this transaction.
     */
    public function originalTransaction(): BelongsTo
    {
        return $this->belongsTo(self::class, 'original_transaction_id');
    }

    /**
     * Get the reversal transaction that voids this transaction.
     */
    public function reversedByTransaction(): BelongsTo
    {
        return $this->belongsTo(self::class, 'reversed_by_transaction_id');
    }

    /**
     * Determine whether this ledger transaction can be reversed by users.
     */
    public function canReverse(): bool
    {
        return ! in_array($this->type, [
            ReservationDepositTransactionType::Opening,
            ReservationDepositTransactionType::Reversal,
        ], true) && ! $this->reversed_by_transaction_id;
    }

    /**
     * Cast the transaction amount to a currency-aware money value.
     *
     * @return Attribute<Money, never>
     */
    public function amount(): Attribute
    {
        return Attribute::get(fn ($amount) => new Money($amount ?? 0, $this->currency));
    }

    /**
     * Cast the pre-transaction balance to a currency-aware money value.
     *
     * @return Attribute<Money, never>
     */
    public function balanceBefore(): Attribute
    {
        return Attribute::get(fn ($amount) => new Money($amount ?? 0, $this->currency));
    }

    /**
     * Cast the post-transaction balance to a currency-aware money value.
     *
     * @return Attribute<Money, never>
     */
    public function balanceAfter(): Attribute
    {
        return Attribute::get(fn ($amount) => new Money($amount ?? 0, $this->currency));
    }

    /**
     * Cast the transaction amount in default currency to a money value.
     *
     * @return Attribute<Money, never>
     */
    public function baseAmount(): Attribute
    {
        return Attribute::get(fn ($amount) => Money::inDefaultCurrency($amount ?? 0));
    }

    /**
     * Cast the pre-transaction balance in default currency to a money value.
     *
     * @return Attribute<Money, never>
     */
    public function baseBalanceBefore(): Attribute
    {
        return Attribute::get(fn ($amount) => Money::inDefaultCurrency($amount ?? 0));
    }

    /**
     * Cast the post-transaction balance in default currency to a money value.
     *
     * @return Attribute<Money, never>
     */
    public function baseBalanceAfter(): Attribute
    {
        return Attribute::get(fn ($amount) => Money::inDefaultCurrency($amount ?? 0));
    }

    /**
     * Get database attributes allowed for transaction sorting.
     *
     * @return list<string>
     */
    protected function getSortableAttributes(): array
    {
        return ['id', 'economic_date', 'type', 'amount', 'created_at'];
    }

    /**
     * Get native and enum casts for ledger fields.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'type' => ReservationDepositTransactionType::class,
            'amount' => 'decimal:4',
            'base_amount' => 'decimal:8',
            'balance_before' => 'decimal:4',
            'base_balance_before' => 'decimal:8',
            'balance_after' => 'decimal:4',
            'base_balance_after' => 'decimal:8',
            'currency_rate' => 'decimal:8',
            'tender_method' => PaymentMethod::class,
            'financial_account_snapshot' => 'array',
            'economic_date' => 'date',
            'reversed_at' => 'datetime',
            'source_snapshot' => 'array',
        ];
    }
}
