<?php

namespace Modules\Order\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Collection;
use Modules\Loyalty\Models\LoyaltyGift;
use Modules\Option\Models\Option;
use Modules\Order\Enums\OrderProductStatus;
use Modules\Order\Pricing\PricingTax;
use Modules\Product\Models\Product;
use Modules\Support\Eloquent\Model;
use Modules\Support\Money;
use Modules\Support\Traits\HasCreatedBy;
use Modules\Support\Traits\HasFilters;
use Modules\Translation\Traits\Translatable;

/**
 * @property int $id
 * @property int $order_id
 * @property-read Order $order
 * @property-read string $name
 * @property int $product_id
 * @property int $prep_time_minutes
 * @property Carbon|null $preparation_started_at
 * @property Carbon|null $prepared_at
 * @property-read Product $product
 * @property int|null $loyalty_gift_id
 * @property-read LoyaltyGift|null $gift
 * @property-read Collection<OrderProductOption> $options
 * @property string $currency
 * @property float $currency_rate
 * @property Money $unit_price
 * @property int $quantity
 * @property Money $subtotal
 * @property Money $tax_total
 * @property Money $total
 * @property Money $cost_price
 * @property Money $gross_profit
 * @property-read Collection<OrderTax> $taxes
 * @property OrderProductStatus $status
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class OrderProduct extends Model
{
    use HasCreatedBy, HasFilters, Translatable;

    /**
     * Default date column
     */
    public static string $defaultDateColumn = 'order_products.created_at';

    /**
     * The attributes that are translatable.
     */
    protected array $translatable = ['product_name'];

    /**
     * The attributes that aren't mass assignable.
     *
     * @var array
     */
    protected $guarded = [];

    /**
     * The relations to eager load on every query.
     *
     * @var array
     */
    protected $with = ['product', 'taxes', 'options'];

    /**
     * Determine if it has any option
     */
    public function hasAnyOption(): bool
    {
        return $this->options->isNotEmpty();
    }

    /**
     * Determine if order product has been deleted.
     */
    public function trashed(): bool
    {
        return $this->product->trashed();
    }

    /**
     * Get product
     */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class)
            ->withOutGlobalBranchPermission()
            ->withoutGlobalActive()
            ->withTrashed();
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
     * Get the order product's name.
     */
    public function name(): Attribute
    {
        return Attribute::get(get: fn () => $this->relationLoaded('product') ? $this->product->name : 'Unknown Product');
    }

    /**
     * Get Unit Price
     */
    public function unitPrice(): Attribute
    {
        return Attribute::get(fn ($amount) => isset($this->currency) ? new Money($amount, $this->currency) : Money::inDefaultCurrency($amount));
    }

    /**
     * Get subtotal
     */
    public function subtotal(): Attribute
    {
        return Attribute::get(fn ($amount) => isset($this->currency) ? new Money($amount, $this->currency) : Money::inDefaultCurrency($amount));
    }

    /**
     * Get tax total
     */
    public function taxTotal(): Attribute
    {
        return Attribute::get(fn ($amount) => isset($this->currency) ? new Money($amount, $this->currency) : Money::inDefaultCurrency($amount));
    }

    /**
     * Get total
     */
    public function total(): Attribute
    {
        return Attribute::get(fn ($amount) => isset($this->currency) ? new Money($amount, $this->currency) : Money::inDefaultCurrency($amount));
    }

    /**
     * Store selected options, reusing a catalog product loaded for the save batch when available.
     */
    public function storeOptions(Collection $options, ?Product $product = null): void
    {
        /** @var Option $option */
        foreach ($options as $option) {
            /** @var OrderProductOption $orderProductOption */
            $orderProductOption = $this->options()
                ->create([
                    'option_id' => $option->id,
                    'value' => $option->type->isFieldType() ? $option->values->first()->label : null,
                ]);

            $orderProductOption->storeValues(
                product: $product ?? $this->product,
                values: $option->values,
                currency: $this->currency,
                currencyRate: $this->currency_rate
            );
        }
    }

    /**
     * Get order product options
     */
    public function options(): HasMany
    {
        return $this->hasMany(OrderProductOption::class)->orderBy('id');
    }

    /**
     * Update Or Create Taxes
     */
    public function updateOrCreateTaxes(Collection $taxes): void
    {
        $this->taxes()
            ->where('order_id', $this->order_id)
            ->whereNotIn('tax_id', $taxes->map(fn (PricingTax $tax) => $tax->id()))
            ->delete();

        foreach ($taxes as $tax) {
            $this->updateOrCreateTax($tax);
        }
    }

    /**
     * Get order product taxes
     */
    public function taxes(): HasMany
    {
        return $this->hasMany(OrderTax::class);
    }

    /**
     * Update Or Create Tax
     */
    public function updateOrCreateTax(PricingTax $tax): void
    {
        $this->taxes()
            ->updateOrCreate(
                [
                    'order_id' => $this->order_id,
                    'tax_id' => $tax->id(),
                ],
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
     * Get total taxes
     */
    public function totalTax(): Money
    {
        $total = 0;

        if ($this->hasTax()) {
            $this->taxes()
                ->get()
                ->each(function ($tax) use (&$total) {
                    $total += $tax->amount->amount();
                });
        }

        return Money::inDefaultCurrency($total);
    }

    /**
     * Determine if order has tax or not
     */
    public function hasTax(): bool
    {
        return $this->taxes->isNotEmpty();
    }

    /**
     * Get order
     */
    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
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
            'branch_id',
        ];
    }

    /**
     * Scope a query to get by branch.
     */
    public function scopeBranchId(Builder $query, string $value): void
    {
        $query->whereHas('order', function (Builder $query) use ($value) {
            $query->where('branch_id', $value);
        });
    }

    /**
     * Scope a query to get by order type.
     */
    public function scopeType(Builder $query, string $value): void
    {
        $query->whereHas('order', function (Builder $query) use ($value) {
            $query->where('type', $value);
        });
    }

    /**
     * Scope a query to get by payment status.
     */
    public function scopePaymentStatus(Builder $query, string $value): void
    {
        $query->whereHas('order', function (Builder $query) use ($value) {
            $query->where('payment_status', $value);
        });
    }

    /**
     * Scope a query to get by status.
     */
    public function scopeStatus(Builder $query, string $value): void
    {
        $query->whereHas('order', function (Builder $query) use ($value) {
            $query->where('status', $value);
        });
    }

    /**
     * Scope a query to search across all fields.
     */
    public function scopeSearch(Builder $query, string $value): void
    {
        $query->whereHas('product', function (Builder $query) use ($value) {
            $query->whereLikeTranslation('name', $value);
        });
    }

    /**
     * Get loyalty gift
     */
    public function gift(): BelongsTo
    {
        return $this->belongsTo(LoyaltyGift::class, 'loyalty_gift_id');
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'start_date' => 'datetime',
            'end_date' => 'datetime',
            'preparation_started_at' => 'datetime',
            'prepared_at' => 'datetime',
            'status' => OrderProductStatus::class,
        ];
    }
}
