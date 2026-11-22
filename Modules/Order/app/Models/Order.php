<?php

namespace Modules\Order\Models;

use Carbon\Carbon;
use DB;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Collection;
use LogicException;
use Modules\Branch\Traits\HasBranch;
use Modules\Core\Contracts\DeliveryFeatureInterface;
use Modules\Core\Contracts\FinancialPostingBridgeInterface;
use Modules\Currency\Currency;
use Modules\Delivery\Models\OrderDelivery;
use Modules\Export\Contracts\ModelExportDefinitionInterface;
use Modules\GiftCard\Enums\GiftCardTransactionType;
use Modules\GiftCard\Services\GiftCard\GiftCardServiceInterface;
use Modules\GiftCard\Services\GiftCardTransaction\GiftCardTransactionServiceInterface;
use Modules\Invoice\Enums\InvoiceKind;
use Modules\Invoice\Models\Invoice;
use Modules\Loyalty\Services\LoyaltyGift\LoyaltyGiftServiceInterface;
use Modules\Order\Enums\DiscountType;
use Modules\Order\Enums\OrderPaymentStatus;
use Modules\Order\Enums\OrderProductStatus;
use Modules\Order\Enums\OrderSource;
use Modules\Order\Enums\OrderStatus;
use Modules\Order\Enums\OrderType;
use Modules\Order\Events\OrderPaid;
use Modules\Order\Events\OrderUpdateStatus;
use Modules\Order\Pricing\PricingCharge;
use Modules\Order\Pricing\PricingDiscount;
use Modules\Order\Pricing\PricingItem;
use Modules\Order\Pricing\PricingTax;
use Modules\Payment\Enums\PaymentMethod;
use Modules\Payment\Enums\PaymentType;
use Modules\Payment\Models\Payment;
use Modules\Pos\Enums\PosCashReason;
use Modules\Pos\Models\PosRegister;
use Modules\Pos\Models\PosSession;
use Modules\Pos\Services\PosCashMovement\PosCashMovementServiceInterface;
use Modules\Product\Models\Product;
use Modules\Reservation\Models\Reservation;
use Modules\SeatingPlan\Models\Table;
use Modules\SeatingPlan\Models\TableMerge;
use Modules\Support\DateRange;
use Modules\Support\Eloquent\Model;
use Modules\Support\Enums\PriceType;
use Modules\Support\Money;
use Modules\Support\Traits\HasCreatedBy;
use Modules\Support\Traits\HasFilters;
use Modules\Support\Traits\HasSortBy;
use Modules\User\Models\User;
use Modules\Voucher\Models\Voucher;
use Throwable;

/**
 * @property int $id
 * @property int|null $table_id
 * @property-read Table|null $table
 * @property int|null $waiter_id
 * @property-read User|null $waiter
 * @property int|null $cashier_id
 * @property-read User|null $cashier
 * @property int|null $pos_register_id
 * @property-read PosRegister|null $posRegister
 * @property int|null $table_merge_id
 * @property-read TableMerge|null $tableMerge
 * @property int|null $merged_into_order_id
 * @property-read Order|null $mergedIntoOrder
 * @property int|null $customer_id
 * @property-read User|null $customer
 * @property int|null $reservation_id
 * @property-read Reservation|null $reservation
 * @property int|null $merged_by
 * @property-read PosRegister|null $mergedBy
 * @property-read Collection<OrderTax> $taxes
 * @property-read Collection<OrderTax> $allTaxes
 * @property-read Collection<OrderProduct> $products
 * @property int|null $pos_session_id
 * @property-read PosSession|null $posSession
 * @property string $reference_no
 * @property string $order_number
 * @property OrderStatus|null $previous_status
 * @property OrderStatus $status
 * @property OrderSource|null $source
 * @property-read  OrderStatus|null $next_status
 * @property OrderType $type
 * @property OrderPaymentStatus $payment_status
 * @property-read OrderDiscount $discount
 * @property-read Collection|Invoice[] $invoices
 * @property-read Collection|Invoice[] $mergedInvoices
 * @property-read Collection|Invoice[] $all_invoices
 * @property-read OrderDelivery $orderDelivery
 * @property string $currency
 * @property float $currency_rate
 * @property Money $subtotal
 * @property Money $total
 * @property Money $due_amount
 * @property Money $cost_price
 * @property Money $gross_profit
 * @property int $guest_count
 * @property string|null $notes
 * @property string|null $car_plate
 * @property string|null $car_description
 * @property Carbon $order_date
 * @property bool $is_stock_deducted
 * @property Carbon|null $stock_bom_snapshotted_at
 * @property Carbon|null $stock_effective_at
 * @property int|null $modified_by
 * @property User|null $modifiedBy
 * @property-read Collection|Payment[] $payments
 * @property Carbon|null $modified_at
 * @property Carbon|null $served_at
 * @property Carbon|null $closed_at
 * @property Carbon|null $merged_at
 * @property Carbon|null $payment_at
 * @property Carbon|null $scheduled_at
 * @property Carbon|null $preparation_started_at
 * @property Carbon|null $prepared_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Collection $charges
 *
 * @method static Builder|static|self activeOrders()
 * @method static Builder|static|self forMerge()
 * @method static Builder|static|self completed()
 * @method static Builder|static|self withoutCanceledOrders()
 * @method static Builder|static|self visibleForKitchen()
 * @method static Builder|static|self forKitchenCategories(array $categoryIds)
 * @method static Builder|static|self whereIdOrReference(int|string $referenceId)
 */
class Order extends Model implements ModelExportDefinitionInterface
{
    use HasBranch,
        HasCreatedBy,
        HasFilters,
        HasSortBy;

    /**
     * Default date column
     */
    public static string $defaultDateColumn = 'created_at';

    /**
     * The attributes that aren't mass assignable.
     *
     * @var array
     */
    protected $guarded = [];

    /** {@inheritDoc} */
    public static function exportColumns(): array
    {
        return [
            ['path' => 'reference_no', 'label' => 'order::orders.table.reference_no'],
            ['path' => 'order_number', 'label' => 'order::orders.table.order_number'],
            ['path' => 'branch.name', 'label' => 'branch::branches.table.branch', 'with' => ['branch:id,name']],
            ['path' => 'customer.name', 'label' => 'order::orders.table.customer', 'with' => ['customer:id,name,phone,phone_country_iso_code']],
            ['path' => 'customer.phone', 'label' => 'user::customers.table.phone', 'with' => ['customer:id,name,phone,phone_country_iso_code']],
            ['path' => 'type', 'label' => 'order::orders.table.type'],
            ['path' => 'source', 'label' => 'order::orders.table.source'],
            ['path' => 'status', 'label' => 'order::orders.table.status'],
            ['path' => 'payment_status', 'label' => 'order::orders.table.payment_status'],
            ['path' => 'subtotal', 'label' => 'order::orders.show.subtotal', 'type' => 'money'],
            ['path' => 'tax_total', 'label' => 'order::orders.total_tax', 'type' => 'money', 'with_sum' => ['relation' => 'allTaxes as tax_total', 'column' => 'amount']],
            ['path' => 'total_discount', 'label' => 'order::orders.show.discount', 'type' => 'money', 'with_sum' => ['relation' => 'discount as total_discount', 'column' => 'amount']],
            ['path' => 'total', 'label' => 'order::orders.table.total', 'type' => 'money'],
            ['path' => 'due_amount', 'label' => 'order::orders.due_amount', 'type' => 'money'],
            ['path' => 'currency', 'label' => 'order::orders.show.currency'],
            ['path' => 'currency_rate', 'label' => 'order::orders.show.currency_rate'],
            ['path' => 'createdBy.name', 'label' => 'order::orders.show.created_by', 'with' => ['createdBy:id,name']],
            ['path' => 'waiter.name', 'label' => 'order::orders.show.waiter', 'with' => ['waiter:id,name']],
            ['path' => 'cashier.name', 'label' => 'order::orders.show.cashier', 'with' => ['cashier:id,name']],
            ['path' => 'posRegister.name', 'label' => 'order::orders.show.pos_register', 'with' => ['posRegister:id,name']],
            ['path' => 'pos_session_id', 'label' => 'order::orders.show.pos_session_id'],
            ['path' => 'products_count', 'label' => 'order::orders.show.products_count', 'with_count' => 'products'],
            ['path' => 'created_at', 'label' => 'admin::admin.table.created_at', 'type' => 'datetime'],
        ];
    }

    /**
     * Get total sales (converted to default currency if needed)
     */
    public static function totalSales(?string $currency = null): Money
    {
        if (auth()->check() && auth()->user()->assignedToBranch()) {
            $total = self::withoutCanceledOrders()->sum('total');
        } else {
            $total = self::withoutCanceledOrders()
                ->selectRaw('SUM(total * COALESCE(currency_rate, 1)) AS total_sales')
                ->value('total_sales') ?? 0;
        }

        return is_null($currency) ? Money::inDefaultCurrency($total) : new Money($total, $currency);
    }

    /**
     * Get average order value (converted to default currency if needed)
     */
    public static function averageOrderValue(?string $currency = null): Money
    {
        if (auth()->check() && auth()->user()->assignedToBranch()) {
            $avg = self::withoutCanceledOrders()->average('total') ?? 0;
        } else {
            $avg = self::withoutCanceledOrders()
                ->selectRaw('AVG(total * COALESCE(currency_rate, 1)) AS avg_order')
                ->value('avg_order') ?? 0;
        }

        return is_null($currency) ? Money::inDefaultCurrency($avg) : new Money($avg, $currency);
    }

    /**
     * Boot the model and handle stock adjustments on create, update, and delete.
     */
    protected static function booted(): void
    {
        static::creating(function (Order $order) {
            $order->reference_no = static::generateReferenceNo();
            $order->order_number = static::generateOrderNumber($order->branch_id);
        });

        static::updating(function (Order $order): void {
            if (! $order->isDirty('status')) {
                return;
            }

            $originalStatus = OrderStatus::tryFrom((string) $order->getRawOriginal('status'));
            if (in_array($originalStatus, [
                OrderStatus::Cancelled,
                OrderStatus::Refunded,
                OrderStatus::Merged,
            ], true) && $order->status !== $originalStatus) {
                throw new LogicException(__('order::messages.terminal_order_status_immutable'));
            }
        });
    }

    /**
     * Generate reference no
     */
    public static function generateReferenceNo(): string
    {
        return mb_strtoupper('ORD-'.substr(base_convert(sha1(uniqid(mt_rand())), 16, 36), 0, 10));
    }

    /**
     * Generate order number
     */
    public static function generateOrderNumber(int $branchId): string
    {
        $count = Order::where('branch_id', $branchId)
            ->where('order_date', '>=', DateRange::start(today()))
            ->where('order_date', '<', DateRange::endExclusive(today()))
            ->count();

        return str_pad($count + 1, $count < 100 ? 2 : 3, '0', STR_PAD_LEFT);
    }

    /**
     * Get a model modified by
     */
    public function modifiedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'modified_by')
            ->withOutGlobalBranchPermission()
            ->withoutGlobalActive()
            ->withTrashed();
    }

    /**
     * Get a waiter
     */
    public function waiter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'waiter_id')
            ->withOutGlobalBranchPermission()
            ->withoutGlobalActive()
            ->withTrashed();
    }

    /**
     * Get cashier
     */
    public function cashier(): BelongsTo
    {
        return $this->belongsTo(User::class, 'cashier_id')
            ->withOutGlobalBranchPermission()
            ->withoutGlobalActive()
            ->withTrashed();
    }

    /**
     * Get customer
     */
    public function customer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'customer_id')
            ->withoutGlobalActive()
            ->withTrashed()
            ->withDefault([
                'id' => null,
                'name' => User::walkInName(),
            ]);
    }

    /**
     * Get the reservation that owns this order.
     */
    public function reservation(): BelongsTo
    {
        return $this->belongsTo(Reservation::class);
    }

    /**
     * Get customer name
     */
    public function getCustomerName(): ?string
    {
        return $this->relationLoaded('customer') ? ($this->customer?->name ?: User::walkInName()) : null;
    }

    /** {@inheritDoc} */
    public function allowedFilterKeys(): array
    {
        return [
            'search',
            'status',
            'payment_status',
            'type',
            'from',
            'to',
            'group_by_date',
            'customer_id',
            self::BRANCH_COLUMN_NAME,
        ];
    }

    /**
     * Match public references without passing text or out-of-range values to a bigint column.
     */
    public function scopeWhereIdOrReference(Builder $query, int|string $identifier): void
    {
        $id = filter_var($identifier, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);

        $query->where(function (Builder $query) use ($identifier, $id): void {
            $query->where('reference_no', (string) $identifier);
            if ($id !== false) {
                $query->orWhere('id', $id);
            }
        });
    }

    /**
     * Scope a query to search across all fields.
     */
    public function scopeSearch(Builder $query, string $value): void
    {
        $query->where(function (Builder $query) use ($value) {
            $query->like('reference_no', $value)->orLike('order_number', $value);
        });
    }

    /**
     * Scope a query to get all active orders.
     */
    public function scopeActiveOrders(Builder $query): Builder
    {
        return $query->where(function ($query) {
            $query
                ->where(function (Builder $query) {
                    $query->whereNot('payment_status', OrderPaymentStatus::Paid)
                        ->orWhereIn('status', [
                            OrderStatus::Pending,
                            OrderStatus::Confirmed,
                            OrderStatus::Preparing,
                            OrderStatus::Ready,
                            OrderStatus::Served,
                        ]);
                })
                ->whereNotIn('status', [
                    OrderStatus::Cancelled,
                    OrderStatus::Refunded,
                    OrderStatus::Merged,
                ]);
        });
    }

    /**
     * Scope a query to get all orders by type.
     */
    public function scopeType(Builder $query, string $type): void
    {
        $query->where('orders.type', $type);
    }

    /**
     * Scope a query to get all orders completed.
     */
    public function scopeCompleted(Builder $query): void
    {
        $query->where('status', OrderStatus::Completed);
    }

    /**
     * Scope a query to get all without canceled orders.
     */
    public function scopeWithoutCanceledOrders(Builder $query): void
    {
        $query->whereNotIn('status', [OrderStatus::Cancelled, OrderStatus::Refunded, OrderStatus::Merged]);
    }

    /**
     * Scope a query to get all active orders.
     */
    public function scopeForMerge(Builder $query): void
    {
        $query->where(function ($query) {
            $query
                ->where('type', OrderType::DineIn)
                ->whereNot('payment_status', OrderPaymentStatus::Paid)
                ->whereIn('status', [
                    OrderStatus::Pending,
                    OrderStatus::Confirmed,
                    OrderStatus::Preparing,
                    OrderStatus::Ready,
                    OrderStatus::Served,
                ]);
        });
    }

    /**
     * Get due amount
     */
    public function dueAmount(): Attribute
    {
        return Attribute::get(fn ($amount) => isset($this->currency) ? new Money($amount, $this->currency) : Money::inDefaultCurrency($amount));
    }

    /**
     * Get cost price
     */
    public function costPrice(): Attribute
    {
        return Attribute::get(fn ($amount) => isset($this->currency) ? new Money($amount, $this->currency) : Money::inDefaultCurrency($amount));
    }

    /**
     * Get gross profit.
     */
    public function grossProfit(): Attribute
    {
        return Attribute::get(fn ($amount) => isset($this->currency) ? new Money($amount, $this->currency) : Money::inDefaultCurrency($amount));
    }

    /**
     * Get total taxes
     */
    public function totalTax(): Money
    {
        $total = ($this->relationLoaded('allTaxes') ? $this->allTaxes : $this->allTaxes()->get())
            ->sum(fn (OrderTax $tax) => $tax->amount->amount());

        return isset($this->currency)
            ? new Money($total, $this->currency)
            : Money::inDefaultCurrency($total);
    }

    /**
     * Get every persisted tax for this order, including product taxes.
     *
     * @return HasMany<OrderTax, static>
     */
    public function allTaxes(): HasMany
    {
        return $this->hasMany(OrderTax::class);
    }

    /**
     * Determine if order has tax or not
     */
    public function hasTax(): bool
    {
        return $this->relationLoaded('allTaxes')
            ? $this->allTaxes->isNotEmpty()
            : $this->allTaxes()->exists();
    }

    /**
     * Determine if order has charges.
     */
    public function hasCharges(): bool
    {
        return $this->relationLoaded('charges')
            ? $this->charges->isNotEmpty()
            : $this->charges()->exists();
    }

    /**
     * Get order charges.
     */
    public function charges(): HasMany
    {
        return $this->hasMany(OrderCharge::class);
    }

    /**
     * Get total charge amount.
     */
    public function chargeTotal(): Attribute
    {
        return Attribute::get(function ($amount) {
            $amount ??= $this->relationLoaded('charges')
                ? $this->charges->sum(fn (OrderCharge $charge) => $charge->amount->amount())
                : $this->charges()->sum('amount');

            return isset($this->currency)
                ? new Money($amount ?? 0, $this->currency)
                : Money::inDefaultCurrency($amount ?? 0);
        });
    }

    /**
     * Determine if order has a discount or not
     */
    public function hasDiscount(): bool
    {
        return ! is_null($this->discount);
    }

    /**
     * Persist a priced line, reusing batch-loaded catalog and saved order models.
     *
     * @throws Throwable
     */
    public function updateOrCreateProduct(PricingItem $cartItem, ?Product $product = null): void
    {
        $giftService = app(LoyaltyGiftServiceInterface::class);

        $params = [
            'product_id' => $cartItem->product->id,
            'currency' => $this->currency,
            'loyalty_gift_id' => $cartItem->loyaltyGift?->id(),
            'currency_rate' => $this->currency_rate,
            'quantity' => $cartItem->qty ?: 1,
            'unit_price' => $cartItem->netUnitPrice(),
            'subtotal' => $cartItem->subtotal(),
            'tax_total' => $cartItem->taxTotal(),
            'total' => $cartItem->total(),
        ];

        if (in_array($cartItem->orderProduct?->status(), [OrderProductStatus::Cancelled, OrderProductStatus::Refunded])) {
            $params['status'] = $cartItem->orderProduct->status();
        }

        if (! is_null($cartItem->orderProduct?->id())) {
            /** @var OrderProduct $orderProduct */
            $orderProduct = $this->relationLoaded('products')
                ? $this->products->firstWhere('id', $cartItem->orderProduct->id())
                : $this->products()
                    ->where('id', $cartItem->orderProduct->id())
                    ->firstOrFail();
            abort_unless($orderProduct, 409, __('order::checkout.order_changed'));
            $orderProduct->update($params);
            // Editable lines must persist the same selections used to calculate their new price.
            // Keep fulfilled and gifted option snapshots unchanged.
            if ($orderProduct->status === OrderProductStatus::Pending && ! $orderProduct->loyalty_gift_id) {
                $orderProduct->options()->delete();
                $orderProduct->storeOptions($cartItem->options, $product);
                $orderProduct->unsetRelation('options');
            }
        } else {
            /** @var OrderProduct $orderProduct */
            $orderProduct = $this->products()->create([
                ...$params,
                'prep_time_minutes' => $cartItem->product->prep_time_minutes,
            ]);
            $orderProduct->storeOptions($cartItem->options, $product);
            if (! is_null($orderProduct->loyalty_gift_id)) {
                $giftService->useGift(loyaltyGiftId: $orderProduct->loyalty_gift_id, order: $this);
            }
        }

        $orderProduct->updateOrCreateTaxes($cartItem->taxes());
    }

    /**
     * Get subtotal
     */
    public function subtotal(): Attribute
    {
        return Attribute::get(fn ($amount) => isset($this->currency) ? new Money($amount, $this->currency) : Money::inDefaultCurrency($amount));
    }

    /**
     * Get tax total.
     */
    public function taxTotal(): Attribute
    {
        return Attribute::get(function ($amount) {
            $amount ??= $this->relationLoaded('allTaxes')
                ? $this->allTaxes->sum(fn (OrderTax $tax) => $tax->amount->amount())
                : $this->allTaxes()->sum('amount');

            return isset($this->currency) ? new Money($amount, $this->currency) : Money::inDefaultCurrency($amount);
        });
    }

    /**
     * Get total
     */
    public function total(): Attribute
    {
        return Attribute::get(fn ($amount) => isset($this->currency) ? new Money($amount, $this->currency) : Money::inDefaultCurrency($amount));
    }

    /**
     * Get products
     */
    public function products(): HasMany
    {
        return $this->hasMany(OrderProduct::class);
    }

    /**
     * Update Or Create Taxes
     */
    public function updateOrCreateTaxes(Collection $taxes): void
    {
        $this->taxes()
            ->whereNotIn('tax_id', $taxes->map(fn (PricingTax $tax) => $tax->id()))
            ->delete();

        foreach ($taxes as $tax) {
            $this->updateOrCreateTax($tax);
        }
    }

    /**
     * Get order taxes
     */
    public function taxes(): HasMany
    {
        return $this->hasMany(OrderTax::class)
            ->whereNull('order_product_id');
    }

    /**
     * Update Or Create Tax
     */
    public function updateOrCreateTax(PricingTax $tax): void
    {
        $this->taxes()
            ->updateOrCreate(
                ['tax_id' => $tax->id()],
                [
                    'name' => $tax->translationsName(),
                    'rate' => $tax->rate(),
                    'currency' => $tax->currency(),
                    'currency_rate' => $this->currency_rate,
                    'amount' => $tax->amount()->amount(),
                    'type' => $tax->type(),
                    'compound' => $tax->compound(),
                ]
            );
    }

    /**
     * Get total discount.
     */
    public function totalDiscount(): Attribute
    {
        return Attribute::get(function ($amount) {
            $amount ??= $this->relationLoaded('discount')
                ? $this->discount?->amount?->amount()
                : $this->discount()->value('amount');

            return isset($this->currency) ? new Money($amount ?? 0, $this->currency) : Money::inDefaultCurrency($amount ?? 0);
        });
    }

    /**
     * Get discount
     *
     * @return HasOne<OrderDiscount,static>
     */
    public function discount(): HasOne
    {
        return $this->hasOne(OrderDiscount::class);
    }

    /**
     * Get products count.
     */
    public function productsCount(): Attribute
    {
        return Attribute::get(fn () => $this->relationLoaded('products') ? $this->products->count() : $this->products()->count());
    }

    /**
     * Update or create order charges.
     *
     * @param  Collection<PricingCharge>  $charges
     */
    public function updateOrCreateCharges(Collection $charges): void
    {
        $codes = $charges
            ->map(fn (PricingCharge $charge) => $charge->code())
            ->values();

        $this->charges()
            ->when(
                $codes->isNotEmpty(),
                fn ($query) => $query->whereNotIn('code', $codes),
                fn ($query) => $query
            )
            ->delete();

        foreach ($charges as $charge) {
            $this->updateOrCreateCharge($charge);
        }
    }

    /**
     * Update or create a single order charge.
     */
    public function updateOrCreateCharge(PricingCharge $charge): void
    {
        $amount = $charge->amount()->amount();

        $this->charges()->updateOrCreate(
            ['code' => $charge->code()],
            [
                'name' => $charge->translations(),
                'currency' => $this->currency,
                'currency_rate' => $this->currency_rate,
                'unit_amount' => $amount,
                'quantity' => 1,
                'amount' => $amount,
                'meta' => $charge->meta(),
            ]
        );
    }

    /**
     * Get pos register
     */
    public function posRegister(): BelongsTo
    {
        return $this->belongsTo(PosRegister::class)
            ->withOutGlobalBranchPermission()
            ->withoutGlobalActive()
            ->withTrashed();
    }

    /**
     * Get pos session
     */
    public function posSession(): BelongsTo
    {
        return $this->belongsTo(PosSession::class)
            ->withOutGlobalBranchPermission()
            ->withTrashed();
    }

    /**
     * Determine if order allow cancel
     */
    public function cancelIsAllowed(): bool
    {
        return $this->payment_status->isUnpaid()
            && ! in_array($this->status, [OrderStatus::Cancelled, OrderStatus::Merged, OrderStatus::Refunded]);
    }

    /**
     * Determine if order allow refund
     */
    public function refundIsAllowed(): bool
    {
        return ! $this->payment_status->isUnpaid()
            && ! in_array($this->status, [OrderStatus::Cancelled, OrderStatus::Refunded, OrderStatus::Merged])
            && ($this->status === OrderStatus::Pending || $this->created_at->diffInHours(now()) < 24);
    }

    /**
     * Determine allow update order status
     */
    public function allowUpdateStatus(): bool
    {
        return ! (is_null($this->next_status)
            || ($this->next_status !== OrderStatus::Confirmed && ! $this->isScheduledForToday())
            || (! $this->payment_status->isPaid() && $this->next_status === OrderStatus::Completed));
    }

    /**
     * Check if the order is scheduled for today (ignores time of day).
     */
    public function isScheduledForToday(): bool
    {
        if ($this->scheduled_at) {
            return $this->scheduled_at->toDateString() <= now()->toDateString();
        }

        return true;
    }

    /**
     * Store status log
     */
    public function storeStatusLog(
        OrderStatus $status,
        ?int $changedById = null,
        ?int $reasonId = null,
        ?string $note = null,
    ): OrderStatusLog {
        return $this->statusLogs()
            ->create([
                'changed_by' => $changedById,
                'reason_id' => $reasonId,
                'status' => $status,
                'note' => $note,
            ]);
    }

    /**
     * Get order status logs
     */
    public function statusLogs(): HasMany
    {
        return $this->hasMany(OrderStatusLog::class)->orderBy('id', 'desc');
    }

    /**
     * Determine if order allow to add payment
     */
    public function allowAddPayment(): bool
    {
        return ! $this->payment_status->isPaid();
    }

    /**
     * Get next status
     */
    public function nextStatus(): Attribute
    {
        return Attribute::get(
            fn () => match ($this->status) {
                OrderStatus::Pending => OrderStatus::Confirmed,
                OrderStatus::Confirmed => OrderStatus::Preparing,
                OrderStatus::Preparing => OrderStatus::Ready,
                OrderStatus::Ready => app(DeliveryFeatureInterface::class)
                    ->resolveNextStatus(
                        $this,
                        $this->type === OrderType::DineIn ? OrderStatus::Served : OrderStatus::Completed
                    ),
                OrderStatus::Served => OrderStatus::Completed,
                default => null
            }
        );
    }

    /**
     * Get previous status
     */
    public function previousStatus(): Attribute
    {
        return Attribute::get(
            fn () => match ($this->status) {
                OrderStatus::Confirmed => OrderStatus::Pending,
                OrderStatus::Preparing => OrderStatus::Confirmed,
                OrderStatus::Ready => OrderStatus::Preparing,
                OrderStatus::Served => OrderStatus::Ready,
                OrderStatus::Completed => $this->type === OrderType::DineIn ? OrderStatus::Served : OrderStatus::Ready,
                default => null
            }
        );
    }

    /**
     * Get table merge
     */
    public function tableMerge(): BelongsTo
    {
        return $this->belongsTo(TableMerge::class)
            ->withOutGlobalBranchPermission()
            ->withTrashed();
    }

    /**
     * Get merged into order
     */
    public function mergedIntoOrder(): BelongsTo
    {
        return $this->belongsTo(Order::class)
            ->withOutGlobalBranchPermission();
    }

    /**
     * Get merged by
     */
    public function mergedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'merged_by')
            ->withOutGlobalBranchPermission()
            ->withoutGlobalActive()
            ->withTrashed();
    }

    /**
     * Recalculate Order
     */
    public function recalculate(bool $deleteTaxesDuplicates = false): void
    {
        if ($deleteTaxesDuplicates) {
            $this->deleteTaxesDuplicates();
        }

        $this->load(['taxes', 'products.product.categories', 'discount.discountable', 'charges']);

        $subtotal = 0;
        $productTaxTotal = 0;
        $costPrice = 0;
        $grossProfit = 0;

        foreach ($this->products as $product) {
            $subtotal += $product->subtotal->amount();
            $productTaxTotal += $product->tax_total->amount();
            $costPrice += $product->cost_price->amount();
            $grossProfit += $product->gross_profit->amount();
        }

        $totalTaxes = $productTaxTotal + $this->recalculateTaxes($subtotal);

        $discountAmount = 0;
        $precision = Currency::subunit($this->currency);

        if (! is_null($this->discount) && $this->discount->discountable) {
            $discountable = $this->discount->discountable;

            $applicableProducts = $this->products->filter(function ($product) use ($discountable) {
                $productSkus = collect($discountable->conditions['products'] ?? []);
                $categories = collect($discountable->conditions['categories'] ?? []);

                $skuMatch = $productSkus->isEmpty() || $productSkus->contains($product->product->sku);
                $categoryMatch = $categories->isEmpty() || $categories->intersect($product->product->categories->pluck('slug'))->isNotEmpty();

                return $skuMatch && $categoryMatch;
            });

            $applicableSubtotal = $applicableProducts->sum(fn ($product) => $product->total->amount());

            if ($discountable->type === PriceType::Percent) {
                $discountAmount = $applicableSubtotal * ($discountable->value / 100);
            } elseif ($discountable->type === PriceType::Fixed) {
                $discountAmount = min($discountable->value->amount(), $applicableSubtotal);
            }

            if (! is_null($discountable->max_discount) && $discountAmount > $discountable->max_discount->amount()) {
                $discountAmount = $discountable->max_discount->amount();
            }

            $this->discount->update(['amount' => round($discountAmount, $precision)]);
        }

        $chargeAmount = $this->charges->sum(fn (OrderCharge $charge) => $charge->amount->amount());
        $totalAfterDiscount = ($subtotal - $discountAmount) + $totalTaxes + $chargeAmount;

        $this->update([
            'subtotal' => round($subtotal, $precision),
            'total' => round($totalAfterDiscount, $precision),
            'cost_price' => round($costPrice, $precision),
            'gross_profit' => round($grossProfit, $precision),
        ]);

        $this->refreshDueAmount();
    }

    /**
     * Delete duplicate taxes by tax_id across given orders.
     *
     * Keeps the first record (lowest id) for each tax_id
     * and deletes the rest.
     *
     * @return int number of deleted rows
     */
    public function deleteTaxesDuplicates(): int
    {
        return DB::table('order_taxes as t1')
            ->join(
                'order_taxes as t2',
                fn ($join) => $join->on('t1.tax_id', '=', 't2.tax_id')
                    ->on('t1.id', '>', 't2.id')
                    ->on('t1.order_id', '=', 't2.order_id')
            )
            ->where('t1.order_id', $this->id)
            ->whereNull('t1.order_product_id')
            ->delete();
    }

    /**
     * Get order table
     */
    public function table(): BelongsTo
    {
        return $this->belongsTo(Table::class)
            ->withOutGlobalBranchPermission()
            ->withoutGlobalActive()
            ->withTrashed();
    }

    /**
     * Recalculate order taxes
     */
    public function recalculateTaxes(float $basePrice): float
    {
        $totalTaxes = 0;

        foreach ($this->taxes as $tax) {
            $taxAmount = $basePrice * ($tax->rate / 100);

            $totalTaxes += $taxAmount;

            if ($tax->compound) {
                $basePrice += $taxAmount;
            }

            $tax->update(['amount' => $taxAmount]);
        }

        return $totalTaxes;
    }

    /**
     * Refresh due amount
     */
    public function refreshDueAmount(): void
    {
        $previousPaymentStatus = $this->payment_status;
        $scale = Currency::subunit($this->currency);
        $factor = 10 ** $scale;

        $totalPaymentsMinor = (int) round(
            $this->payments()->whereNull('voided_at')->where('type', PaymentType::Payment)->sum('amount') * $factor
        );

        $totalRefundsMinor = (int) round(
            $this->payments()->whereNull('voided_at')->where('type', PaymentType::Refund)->sum('amount') * $factor
        );

        $paidMinor = $totalPaymentsMinor - $totalRefundsMinor;

        $totalRounded = round($this->total->amount(), $scale);
        $totalMinor = (int) round($totalRounded * $factor);

        $dueMinor = max($totalMinor - $paidMinor, 0);

        if ($paidMinor <= 0) {
            $paymentStatus = OrderPaymentStatus::Unpaid;
        } elseif ($paidMinor < $totalMinor) {
            $paymentStatus = OrderPaymentStatus::PartiallyPaid;
        } else {
            $paymentStatus = OrderPaymentStatus::Paid;
        }

        $this->updateQuietly([
            'due_amount' => $dueMinor / $factor,
            'payment_status' => $paymentStatus,
            'payment_at' => $paymentStatus === OrderPaymentStatus::Paid ? now() : null,
        ]);

        if (
            $paymentStatus === OrderPaymentStatus::Paid
            && is_null($this->table_merge_id)
            && (! $previousPaymentStatus?->isPaid())
        ) {
            event(new OrderPaid($this));
        }
    }

    /**
     * Get order payments
     */
    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class)
            ->withOutGlobalBranchPermission()
            ->withTrashed();
    }

    /**
     * Determine if order have refund amount
     */
    public function hasRefundAmount(): bool
    {
        return $this->getRefundedAmount()->amount() > 0 && ! $this->payment_status->isUnpaid();
    }

    /**
     * Get refunded amount
     */
    public function getRefundedAmount(): Money
    {
        return $this->total->subtract($this->due_amount);
    }

    /**
     * Determine allow edit order or not
     */
    public function editIsAllowed(): bool
    {
        $allowed = ! $this->payment_status->isPaid()
            && in_array(
                $this->status,
                [
                    OrderStatus::Pending,
                    OrderStatus::Confirmed,
                    OrderStatus::Preparing,
                    OrderStatus::Ready,
                    OrderStatus::Served,
                ]
            );

        if (! $allowed) {
            return false;
        }

        return app(DeliveryFeatureInterface::class)->allowsOrderEdit($this);
    }

    /**
     * Get order delivery.
     */
    public function orderDelivery(): HasOne
    {
        $related = app(DeliveryFeatureInterface::class)->orderDeliveryModelClass();

        return is_null($related)
            ? $this->hasOne(self::class, 'id', 'id')->whereRaw('1 = 0')
            : $this->hasOne($related);
    }

    /**
     * Update Or Create Discount
     *
     * @throws Throwable
     */
    public function updateOrCreateDiscount(?PricingDiscount $discount): void
    {
        /** @var OrderDiscount|null $orderDiscount */
        $orderDiscount = $this->discount;
        $oldLoyaltyGiftId = $orderDiscount?->loyalty_gift_id;

        $amount = $discount?->value();

        if (is_null($amount) || $amount->isZero()) {
            $discount = null;
        }

        $giftService = app(LoyaltyGiftServiceInterface::class);

        if (! is_null($oldLoyaltyGiftId) && (int) $oldLoyaltyGiftId !== (int) $discount?->loyaltyGift()?->id()) {
            $giftService->rollbackGift(loyaltyGiftId: $oldLoyaltyGiftId, order: $this);
        }

        if ((is_null($discount) || $amount->isZero()) && $orderDiscount) {
            $orderDiscount->discountable?->unusedOnce();
            $orderDiscount->delete();

            return;
        }

        if (is_null($discount)) {
            return;
        }

        $discountModel = $discount->model();

        $data = [
            'discountable_type' => $discountModel->getMorphClass(),
            'discountable_id' => $discountModel->id,
            'type' => $discountModel instanceof Voucher
                ? DiscountType::Voucher
                : DiscountType::Discount,
            'currency' => $this->currency,
            'currency_rate' => $this->currency_rate,
            'loyalty_gift_id' => $discount->loyaltyGift()?->id(),
            'amount' => $amount->amount(),
        ];

        if ($orderDiscount) {
            $oldType = $orderDiscount->discountable_type;
            $oldId = $orderDiscount->discountable_id;

            $orderDiscount->update($data);

            if ($oldType !== $data['discountable_type'] || $oldId !== $data['discountable_id']) {
                $orderDiscount->discountable?->unusedOnce();
                $discountModel->usedOnce();
            }

            return;
        }

        /** @var OrderDiscount $orderDiscount */
        $orderDiscount = $this->discount()->create($data);
        $discountModel->usedOnce();

        if (! is_null($orderDiscount->loyalty_gift_id) && (int) $oldLoyaltyGiftId !== (int) $orderDiscount->loyalty_gift_id) {
            $giftService->useGift(loyaltyGiftId: $orderDiscount->loyalty_gift_id, order: $this);
        }
    }

    /**
     * Revert the order status back to "Preparing" if the order
     * is not in Pending or Confirmed status but still contains
     * products with a Pending status.
     *
     * This typically happens when an already-progressed order
     * gets modified (e.g., items added/updated), requiring the status
     * to roll back to Preparing.
     */
    public function revertOrderStatusToPreparingIfModified(): void
    {
        if (
            ! in_array($this->status, [OrderStatus::Pending, OrderStatus::Confirmed, OrderStatus::Preparing])
            && $this->products()->where('status', OrderProductStatus::Pending)->exists()
        ) {
            $this->update([
                'status' => OrderStatus::Preparing,
            ]);

            event(new OrderUpdateStatus(
                order: $this,
                status: OrderStatus::Preparing,
                note: 'Order was modified. Status changed back to Preparing.',
            ));
        }
    }

    /**
     * Handle overpayment logic when an order is modified.
     *
     * If the order has an overpaid amount, a refund payment is created.
     * Otherwise, the order due amount is recalculated.
     *
     * @param  array  $data
     *                       Expected keys:
     *                       - refund_payment_method
     *                       - session_id
     *                       - currency_rate
     *
     * @throws Throwable
     */
    public function handleOverpaymentAdjustment(array $data): void
    {
        if ($data['overpaid_amount'] > 0) {
            $this->storePayment([
                'cashier_id' => auth()->id(),
                'method' => $data['refund_payment_method'],
                'amount' => $data['overpaid_amount'],
                'meta' => [
                    'reason' => 'Order changed and overpaid',
                    ...($data['refund_payment_method'] === PaymentMethod::GiftCard->value
                        ? ['gift_card_code' => $data['gift_card_code'] ?? null]
                        : []),
                ],
                'session' => PosSession::findOrFail($data['session_id']),
                'type' => PaymentType::Refund->value,
                'currency_rate' => $data['currency_rate'],
                'notes' => 'Order changed and overpaid',
            ]);
        }
        $this->refreshDueAmount();
    }

    /**
     * Store payment
     *
     * @throws Throwable
     */
    public function storePayment(array $data): Payment
    {
        return DB::transaction(function () use ($data): Payment {
            // Standalone callers follow the same order → session lock order as checkout and late collection.
            $order = static::query()->lockForUpdate()->findOrFail($this->id);

            return $order->storePaymentInTransaction($data);
        }, 3);
    }

    /**
     * Commit the attributed receipt, drawer movement and payment effects under the session's closing lock.
     */
    private function storePaymentInTransaction(array $data): Payment
    {
        $session = isset($data['session'])
            ? PosSession::query()->lockForUpdate()->findOrFail($data['session']->id)
            : null;
        if (! $session && $this->pos_session_id) {
            // Unassigned applications remain excluded, but serialize their warning count with closing.
            PosSession::query()
                ->withOutGlobalBranchPermission()
                ->withTrashed()
                ->whereKey($this->pos_session_id)
                ->lockForUpdate()
                ->first();
        }
        abort_unless(
            ! $session || ((int) $session->branch_id === (int) $this->branch_id && ! $session->isClosed()),
            409,
            __('order::checkout.session_changed'),
        );
        abort_if(
            $session && $session->branch->currency !== $this->currency,
            422,
            __('order::messages.payment_session_currency_mismatch'),
        );
        $data['session'] = $session;
        $data = $this->resolveGiftCardPaymentMeta($data);
        $meta = [
            'payment_kind' => data_get($data, 'meta.payment_kind', 'original'),
            ...(is_array($data['meta'] ?? null) ? $data['meta'] : []),
        ];

        /** @var Payment $payment */
        $payment = $this->payments()->create([
            'order_reference_no' => $this->reference_no,
            'branch_id' => $this->branch_id,
            'cashier_id' => $data['cashier_id'] ?? null,
            'pos_session_id' => $session?->id,
            'transaction_id' => $data['transaction_id'] ?? null,
            'method' => $data['method'],
            'amount' => $data['amount'],
            'type' => $data['type'] ?? PaymentType::Payment,
            'currency' => $this->currency,
            'currency_rate' => $data['currency_rate'] ?? $this->currency_rate,
            'meta' => $meta,
            'received_at' => $data['received_at'] ?? now(),
            'received_by' => $data['received_by'] ?? auth()->id(),
        ]);

        $this->handleGiftCardPayment($payment, $data);

        $shouldCreatePosCashMovement = $data['method'] === PaymentMethod::Cash->value
            && ! ($data['skip_pos_cash_movement'] ?? false)
            && ! empty($data['session']);

        if ($shouldCreatePosCashMovement) {
            if ($payment->type === PaymentType::Refund) {
                app(PosCashMovementServiceInterface::class)
                    ->refundCash(
                        session: $data['session'],
                        amount: abs($data['amount']),
                        orderId: $this->id,
                        paymentId: $payment->id,
                        reference: $this->reference_no,
                        notes: $data['notes'] ?? null,
                    );
            } else {
                app(PosCashMovementServiceInterface::class)
                    ->sale(
                        session: $data['session'],
                        amount: abs($data['amount']),
                        orderId: $this->id,
                        paymentId: $payment->id,
                    );
            }
        }

        $this->recordAccountingPaymentEvent($payment);

        return $payment;
    }

    /**
     * Resolve and normalize gift card metadata before persisting the payment row.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function resolveGiftCardPaymentMeta(array $data): array
    {
        if (($data['method'] ?? null) !== PaymentMethod::GiftCard->value || empty($data['meta']['gift_card_code'])) {
            return $data;
        }

        $giftCard = app(GiftCardServiceInterface::class)->findOrFail($data['meta']['gift_card_code']);

        abort_if(
            $giftCard->customer_id && $giftCard->customer_id !== $this->customer_id,
            422,
            __('giftcard::messages.card_customer_mismatch')
        );

        return [
            ...$data,
            'meta' => [
                ...($data['meta'] ?? []),
                'gift_card_code' => $giftCard->code,
                'gift_card_id' => $giftCard->id,
            ],
        ];
    }

    /**
     * Handle gift card side effects after the payment row has been created.
     *
     * @throws Throwable
     */
    protected function handleGiftCardPayment(Payment $payment, array $data): void
    {
        if ($payment->method !== PaymentMethod::GiftCard || $payment->type !== PaymentType::Refund) {
            return;
        }

        $giftCardCode = $payment->meta['gift_card_code'] ?? null;
        if (blank($giftCardCode)) {
            return;
        }

        $giftCard = app(GiftCardServiceInterface::class)->findOrFail($giftCardCode);
        $giftCardCurrencyRate = app(GiftCardServiceInterface::class)->currencyRateToDefault($giftCard->currency);
        $refundAmount = app(GiftCardServiceInterface::class)->convertOrderAmountToGiftCardAmount(
            giftCard: $giftCard,
            amount: $payment->amount->amount(),
            orderCurrency: $this->currency,
            orderCurrencyRate: $payment->currency_rate,
            giftCardCurrencyRate: $giftCardCurrencyRate,
        );

        app(GiftCardTransactionServiceInterface::class)
            ->record($giftCard, [
                'type' => GiftCardTransactionType::Refund,
                'amount' => $refundAmount->amount(),
                'amount_in_order_currency' => $payment->amount->amount(),
                'order_currency' => $this->currency,
                'exchange_rate' => $payment->currency_rate,
                'currency_rate' => $giftCardCurrencyRate,
                'payment_id' => $payment->id,
                'idempotency_key' => "gift_card:$giftCard->id:refund:payment:$payment->id",
                'economic_date' => $payment->received_at?->toDateString(),
                'order_id' => $this->id,
                'branch_id' => $this->branch_id,
                'notes' => $data['notes'] ?? null,
            ]);
    }

    /**
     * Record a payment source event for optional Accounting posting.
     */
    protected function recordAccountingPaymentEvent(Payment $payment, ?string $event = null): void
    {
        try {
            $event ??= $payment->type === PaymentType::Refund ? 'refund' : 'received';

            app(FinancialPostingBridgeInterface::class)
                ->recordEvent(
                    'payment',
                    $payment->id,
                    $event,
                    [
                        'branch_id' => $payment->branch_id,
                        'order_id' => $payment->order_id,
                        'order_reference_no' => $payment->order_reference_no,
                        'transaction_id' => $payment->transaction_id,
                        'method' => $payment->method?->value,
                        'type' => $payment->type?->value,
                        'amount' => $payment->amount?->amount(),
                        'currency' => $payment->currency,
                        'currency_rate' => $payment->currency_rate,
                        'received_at' => $payment->received_at?->toDateTimeString(),
                        'voided_at' => $payment->voided_at?->toDateTimeString(),
                    ]
                );
        } catch (Throwable) {
            // Accounting is optional and must not block payment workflows.
        }
    }

    /**
     * Mark a payment row as voided without deleting the audit trail.
     *
     * @throws Throwable
     */
    public function voidPayment(Payment $payment, ?string $reason = null, ?int $voidedBy = null): void
    {
        DB::transaction(function () use ($payment, $reason, $voidedBy): void {
            $order = static::query()->lockForUpdate()->findOrFail($this->id);
            /** @var Payment $storedPayment */
            $storedPayment = $order->payments()->findOrFail($payment->id);
            $collectingSessionIds = $storedPayment->pos_session_id
                ? collect([$storedPayment->pos_session_id])
                : ($storedPayment->method === PaymentMethod::Cash
                    ? $storedPayment->cashMovements()
                        ->whereIn('reason', [PosCashReason::Sale, PosCashReason::Refund])
                        ->pluck('pos_session_id')->unique()
                    : collect());
            $sessionIds = $collectingSessionIds->all();
            if (! $storedPayment->pos_session_id && $order->pos_session_id) {
                $sessionIds[] = $order->pos_session_id;
            }
            // Session precedes payment/gift-card locks, matching receipt creation and drawer writes.
            $sessions = PosSession::query()
                ->withOutGlobalBranchPermission()
                ->withTrashed()
                ->whereIn('id', $sessionIds)
                ->orderBy('id')
                ->lockForUpdate()
                ->get()
                ->keyBy('id');

            /** @var Payment $lockedPayment */
            $lockedPayment = $order->payments()->lockForUpdate()->findOrFail($payment->id);
            if ($lockedPayment->isVoided()) {
                return;
            }

            foreach ($collectingSessionIds as $sessionId) {
                $session = $sessions->get($sessionId);
                abort_unless(
                    $session && ! $session->isClosed() && ! $session->trashed()
                        && (int) $session->branch_id === (int) $order->branch_id,
                    409,
                    __('order::checkout.session_changed'),
                );
            }
            if ($lockedPayment->method === PaymentMethod::GiftCard) {
                app(GiftCardTransactionServiceInterface::class)->reversePaymentTransaction($lockedPayment->id, [
                    'idempotency_key' => "gift_card:payment:$lockedPayment->id:voided",
                    'economic_date' => now()->toDateString(),
                    'reason' => $reason ?: 'Linked payment voided.',
                ]);
            }
            $lockedPayment->update([
                'voided_at' => $lockedPayment->voided_at ?: now(),
                'voided_by' => $lockedPayment->voided_by ?: ($voidedBy ?? auth()->id()),
                'void_reason' => $lockedPayment->void_reason ?: $reason,
            ]);
        }, 3);

        $this->recordAccountingPaymentEvent($payment->fresh(), 'voided');
    }

    /**
     * Store payments
     *
     * @throws Throwable
     */
    public function storePayments(array $payments): array
    {
        $stored = [];
        foreach ($payments as $payment) {
            $stored[] = $this->storePayment($payment);
        }

        $this->refreshDueAmount();

        return $stored;
    }

    /**
     * Get invoices
     */
    public function invoices(): HasMany
    {
        return $this->hasMany(Invoice::class);
    }

    public function getInvoice($with = []): ?Invoice
    {
        return Invoice::with($with)
            ->where('invoice_kind', InvoiceKind::Standard)
            ->when(
                is_null($this->table_merge_id),
                fn ($query) => $query->where('order_id', $this->id)
            )
            ->when(
                ! is_null($this->table_merge_id),
                fn ($query) => $query->where('table_merge_id', $this->table_merge_id)
            )
            ->first();
    }

    /**
     * Get merged invoices
     */
    public function mergedInvoices(): HasMany
    {
        return $this->hasMany(Invoice::class, 'table_merge_id');
    }

    /**
     * Visible For Kitchen
     */
    public function scopeVisibleForKitchen(Builder $query): void
    {
        $recentlyVisibleStatuses = [
            OrderStatus::Ready,
            OrderStatus::Served,
        ];

        $query->where(function ($query) use ($recentlyVisibleStatuses) {
            $query->whereNotIn('status', $recentlyVisibleStatuses)
                ->when(setting('kitchen_show_served_orders_enabled', true), function ($query) use ($recentlyVisibleStatuses) {
                    $query->orWhereHas('statusLogs', function ($log) use ($recentlyVisibleStatuses) {
                        $log->whereIn('status', $recentlyVisibleStatuses)
                            ->where(
                                'created_at',
                                '>=',
                                now()->subMinutes(
                                    min(
                                        20,
                                        max(1, (int) setting('kitchen_show_served_orders_minutes', 5))
                                    )
                                ));
                    });
                });
        });
    }

    /**
     * For kitchen categories
     */
    public function scopeForKitchenCategories(Builder $query, array $categoryIds): void
    {
        if (! empty($categoryIds)) {
            $query->whereHas('products', function ($q) use ($categoryIds) {
                $q->whereHas('product.categories', function ($q) use ($categoryIds) {
                    $q->whereIn('categories.id', $categoryIds);
                });
            });
        }
    }

    /**
     * Re-Calculate Order Status
     */
    public function recalculateOrderStatus(): void
    {
        $statuses = $this->products()
            ->whereNotIn('status', [
                OrderProductStatus::Cancelled,
                OrderProductStatus::Refunded,
            ])
            ->pluck('status')
            ->unique();

        $newStatus = match (true) {
            $statuses->contains(OrderProductStatus::Pending)
            || $statuses->contains(OrderProductStatus::Preparing) => OrderStatus::Preparing,
            $statuses->every(fn ($s) => $s === OrderProductStatus::Served) => OrderStatus::Served,
            $statuses->contains(OrderProductStatus::Ready) => OrderStatus::Ready,
            default => OrderStatus::Confirmed,
        };

        if ($this->status !== $newStatus) {
            $this->update(['status' => $newStatus]);

            event(new OrderUpdateStatus(
                order: $this,
                status: $newStatus,
                changedById: auth()->id(),
                stopUpdateOrderProductStatus: true
            ));
        }
    }

    /**
     * All distinct invoices using Laravel 12 Attribute Cast.
     */
    protected function allInvoices(): Attribute
    {
        return Attribute::get(function () {
            $invoices = $this->relationLoaded('invoices')
                ? $this->getRelation('invoices')
                : collect();

            $merged = $this->relationLoaded('mergedInvoices')
                ? $this->getRelation('mergedInvoices')
                : collect();

            return $invoices
                ->merge($merged)
                ->unique('id')
                ->values();
        });
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => OrderStatus::class,
            'source' => OrderSource::class,
            'type' => OrderType::class,
            'payment_status' => OrderPaymentStatus::class,
            'previous_status' => OrderStatus::class,
            'order_date' => 'date',
            'served_at' => 'datetime',
            'closed_at' => 'datetime',
            'merged_at' => 'datetime',
            'payment_at' => 'datetime',
            'modified_at' => 'datetime',
            'is_stock_deducted' => 'boolean',
            'stock_bom_snapshotted_at' => 'datetime',
            'stock_effective_at' => 'datetime',
            'start_date' => 'datetime',
            'end_date' => 'datetime',
            'scheduled_at' => 'datetime',
            'preparation_started_at' => 'datetime',
            'prepared_at' => 'datetime',
        ];
    }

    /** {@inheritDoc} */
    protected function getSortableAttributes(): array
    {
        return [
            'reference_no',
            'order_number',
            'status',
            'type',
            'total',
            'payment_status',
            'customer_id',
            self::BRANCH_COLUMN_NAME,
        ];
    }
}
