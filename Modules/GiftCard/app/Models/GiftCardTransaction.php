<?php

namespace Modules\GiftCard\Models;

use Brick\Math\BigDecimal;
use Brick\Math\Exception\NumberFormatException;
use Brick\Math\RoundingMode;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;
use LogicException;
use Modules\Branch\Traits\HasBranch;
use Modules\Export\Contracts\ModelExportDefinitionInterface;
use Modules\GiftCard\Database\Factories\GiftCardTransactionFactory;
use Modules\GiftCard\Enums\GiftCardTransactionType;
use Modules\Order\Models\Order;
use Modules\Payment\Models\Payment;
use Modules\Support\Eloquent\Model;
use Modules\Support\Money;
use Modules\Support\Traits\HasCreatedBy;
use Modules\Support\Traits\HasFilters;
use Modules\Support\Traits\HasSortBy;

/**
 * Represents an immutable ledger entry for changes applied to a gift card balance.
 *
 * @property int $id
 * @property string $uuid
 * @property int|null $branch_id
 * @property int|null $created_by
 * @property int $gift_card_id
 * @property int|null $order_id
 * @property GiftCardTransactionType $type
 * @property Money $amount
 * @property Money $balance_before
 * @property Money $balance_after
 * @property-read Money|null $amount_in_base_currency
 * @property string $currency
 * @property float|null $exchange_rate Informational order-to-card exchange rate.
 * @property string $currency_rate Frozen Gift Card currency-to-base-currency rate.
 * @property string|null $currency_rate_source
 * @property int|null $currency_rate_reviewed_by
 * @property Carbon|null $currency_rate_reviewed_at
 * @property string|null $currency_rate_review_note
 * @property string|null $funding_type
 * @property string|null $tender_method
 * @property string|null $financial_account_key
 * @property array|null $financial_account_snapshot
 * @property string|null $idempotency_key
 * @property string|null $request_fingerprint
 * @property int|null $payment_id
 * @property int|null $original_transaction_id
 * @property int|null $reversed_by_transaction_id
 * @property Carbon|null $economic_date
 * @property array|null $source_snapshot
 * @property Carbon|null $reversed_at
 * @property string|null $order_currency
 * @property Money|null $amount_in_order_currency
 * @property string|null $notes
 * @property Carbon|null $transaction_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read GiftCard|null $giftCard
 * @property-read Order|null $order
 * @property-read Payment|null $payment
 * @property-read GiftCardTransaction|null $originalTransaction
 * @property-read GiftCardTransaction|null $reversedByTransaction
 */
class GiftCardTransaction extends Model implements ModelExportDefinitionInterface
{
    use HasBranch,
        HasCreatedBy,
        HasFactory,
        HasFilters,
        HasSortBy;

    public static string $defaultDateColumn = 'transaction_at';

    /**
     * The attributes that are mass assignable.
     */
    protected $fillable = [
        'uuid',
        'idempotency_key',
        'request_fingerprint',
        'gift_card_id',
        'type',
        'amount',
        'balance_before',
        'balance_after',
        'currency',
        'exchange_rate',
        'currency_rate',
        'currency_rate_source',
        'currency_rate_reviewed_by',
        'currency_rate_reviewed_at',
        'currency_rate_review_note',
        'funding_type',
        'tender_method',
        'financial_account_key',
        'financial_account_snapshot',
        'amount_in_order_currency',
        'order_currency',
        'order_id',
        'payment_id',
        'original_transaction_id',
        'reversed_by_transaction_id',
        'branch_id',
        'notes',
        'transaction_at',
        'economic_date',
        'source_snapshot',
        'reversed_at',
        'group_by_date',
        'created_by',
    ];

    /** {@inheritDoc} */
    public static function exportColumns(): array
    {
        return [
            ['path' => 'id', 'label' => 'admin::admin.table.id'],
            ['path' => 'uuid', 'label' => 'giftcard::gift_card_transactions.show.uuid'],
            ['path' => 'giftCard.code', 'label' => 'giftcard::gift_card_transactions.table.gift_card', 'with' => ['giftCard:id,code']],
            ['path' => 'type', 'label' => 'giftcard::gift_card_transactions.table.type'],
            ['path' => 'amount', 'label' => 'giftcard::gift_card_transactions.table.amount', 'type' => 'money'],
            ['path' => 'balance_before', 'label' => 'giftcard::gift_card_transactions.table.balance_before', 'type' => 'money'],
            ['path' => 'balance_after', 'label' => 'giftcard::gift_card_transactions.table.balance_after', 'type' => 'money'],
            ['path' => 'currency', 'label' => 'giftcard::gift_card_transactions.table.currency'],
            ['path' => 'exchange_rate', 'label' => 'giftcard::gift_card_transactions.table.exchange_rate'],
            ['path' => 'currency_rate', 'label' => 'giftcard::gift_card_transactions.table.currency_rate'],
            ['path' => 'currency_rate_source', 'label' => 'giftcard::gift_card_transactions.table.currency_rate_source'],
            ['path' => 'order_currency', 'label' => 'giftcard::gift_card_transactions.table.order_currency'],
            ['path' => 'amount_in_order_currency', 'label' => 'giftcard::gift_card_transactions.table.amount_in_order_currency', 'type' => 'money'],
            ['path' => 'branch.name', 'label' => 'giftcard::gift_card_transactions.table.branch', 'with' => ['branch:id,name']],
            ['path' => 'order.reference_no', 'label' => 'giftcard::gift_card_transactions.table.order', 'with' => ['order:id,reference_no']],
            ['path' => 'createdBy.name', 'label' => 'giftcard::gift_card_transactions.table.user', 'with' => ['createdBy:id,name']],
            ['path' => 'notes', 'label' => 'giftcard::gift_card_transactions.table.notes'],
            ['path' => 'transaction_at', 'label' => 'giftcard::gift_card_transactions.table.transaction_at', 'type' => 'datetime'],
        ];
    }

    /**
     * Create the model factory used by tests and database seeders.
     */
    protected static function newFactory(): GiftCardTransactionFactory
    {
        return GiftCardTransactionFactory::new();
    }

    /**
     * Initialize generated fields before persisting a new transaction row.
     */
    protected static function booted(): void
    {
        static::creating(function (GiftCardTransaction $transaction) {
            if (! $transaction->uuid) {
                $transaction->uuid = (string) Str::uuid();
            }

            if (! $transaction->transaction_at) {
                $transaction->transaction_at = now();
            }
        });

        static::updating(function (GiftCardTransaction $transaction): void {
            $allowed = ['reversed_by_transaction_id', 'reversed_at', 'updated_at'];
            if (array_diff(array_keys($transaction->getDirty()), $allowed) !== []) {
                throw new LogicException(__('giftcard::messages.transaction_immutable'));
            }
        });

        static::deleting(function (): void {
            throw new LogicException(__('giftcard::messages.transaction_not_deletable'));
        });
    }

    /**
     * Get the supported filter keys for transaction listing endpoints.
     *
     * @return array<int, string>
     */
    public function allowedFilterKeys(): array
    {
        return [
            'search',
            'from',
            'to',
            self::BRANCH_COLUMN_NAME,
            'type',
            'gift_card_id',
            'created_by',
            'currency',
            'order_currency',
            'group_by_date',
        ];
    }

    /**
     * Apply a text search over the transaction, linked card, and linked order.
     */
    public function scopeSearch(Builder $query, string $value): void
    {
        $query->where(function (Builder $builder) use ($value) {
            $builder->like('uuid', $value)
                ->orLike('notes', $value)
                ->orWhereHas('giftCard', fn (Builder $giftCardQuery) => $giftCardQuery->like('code', $value))
                ->orWhereHas('order', fn (Builder $orderQuery) => $orderQuery->where('reference_no', $value));
        });
    }

    /**
     * Cast the transaction amount into a Money value object.
     *
     * @return Attribute<Money, static>
     */
    public function amount(): Attribute
    {
        return Attribute::get(fn ($amount) => new Money($amount, $this->currency));
    }

    /**
     * Cast the previous balance into a Money value object.
     *
     * @return Attribute<Money, static>
     */
    public function balanceBefore(): Attribute
    {
        return Attribute::get(fn ($amount) => new Money($amount, $this->currency));
    }

    /**
     * Cast the resulting balance into a Money value object.
     *
     * @return Attribute<Money, static>
     */
    public function balanceAfter(): Attribute
    {
        return Attribute::get(fn ($amount) => new Money($amount, $this->currency));
    }

    /**
     * Cast the order-currency amount into a Money value object.
     *
     * @return Attribute<Money|null, static>
     */
    public function amountInOrderCurrency(): Attribute
    {
        return Attribute::get(function ($amount) {
            if (is_null($amount) || blank($this->order_currency)) {
                return null;
            }

            return new Money($amount, $this->order_currency);
        });
    }

    /**
     * Convert the frozen Gift Card transaction amount to the default currency.
     *
     * Gift Card currency rates are stored as one card-currency unit expressed
     * in the default currency, so the conversion intentionally multiplies by
     * the immutable transaction rate.
     *
     * Legacy transactions without a reviewed frozen rate intentionally return
     * null until the remediation workflow supplies immutable rate evidence.
     *
     * @return Attribute<Money|null, never>
     */
    public function amountInBaseCurrency(): Attribute
    {
        return Attribute::get(function (): ?Money {
            $amount = $this->getRawOriginal('amount') ?? $this->getAttributes()['amount'] ?? 0;
            $currencyRate = $this->getRawOriginal('currency_rate') ?? $this->getAttributes()['currency_rate'] ?? null;
            if (blank($currencyRate)) {
                return null;
            }

            try {
                $rate = BigDecimal::of((string) $currencyRate);
                if ($rate->isLessThanOrEqualTo(BigDecimal::zero())) {
                    return null;
                }

                $baseAmount = BigDecimal::of((string) $amount)
                    ->multipliedBy($rate)
                    ->toScale(4, RoundingMode::HalfUp);
            } catch (NumberFormatException) {
                return null;
            }

            return new Money((float) (string) $baseAmount, (string) setting('default_currency', 'JOD'));
        });
    }

    /**
     * Get the gift card that owns this ledger transaction.
     *
     * @return BelongsTo<GiftCard, $this>
     */
    public function giftCard(): BelongsTo
    {
        return $this->belongsTo(GiftCard::class);
    }

    /**
     * Get the related order when the transaction is tied to order activity.
     *
     * @return BelongsTo<Order, $this>
     */
    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class)->withOutGlobalBranchPermission();
    }

    /**
     * Get the linked Payment that owns a redemption or refund, when applicable.
     *
     * @return BelongsTo<Payment, $this>
     */
    public function payment(): BelongsTo
    {
        return $this->belongsTo(Payment::class)->withOutGlobalBranchPermission()->withTrashed();
    }

    /**
     * Get the original immutable transaction that this row reverses.
     *
     * @return BelongsTo<GiftCardTransaction, $this>
     */
    public function originalTransaction(): BelongsTo
    {
        return $this->belongsTo(self::class, 'original_transaction_id');
    }

    /**
     * Get the reversal transaction that cancels this row, when one exists.
     *
     * @return BelongsTo<GiftCardTransaction, $this>
     */
    public function reversedByTransaction(): BelongsTo
    {
        return $this->belongsTo(self::class, 'reversed_by_transaction_id');
    }

    /**
     * Get the sortable attributes supported by the listing layer.
     *
     * @return array<int, string>
     */
    protected function getSortableAttributes(): array
    {
        return [
            'transaction_at',
            'type',
            'amount',
            'balance_before',
            'balance_after',
            'branch_id',
            'created_by',
        ];
    }

    /**
     * Cast model attributes to their domain-specific value objects and enums.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'type' => GiftCardTransactionType::class,
            'transaction_at' => 'datetime',
            'exchange_rate' => 'float',
            'currency_rate' => 'decimal:8',
            'currency_rate_reviewed_at' => 'datetime',
            'financial_account_snapshot' => 'array',
            'economic_date' => 'date',
            'source_snapshot' => 'array',
            'reversed_at' => 'datetime',
        ];
    }
}
