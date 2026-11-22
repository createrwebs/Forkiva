<?php

namespace Modules\Discount\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\MorphOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Modules\ActivityLog\Traits\HasActivityLog;
use Modules\Branch\Traits\HasBranch;
use Modules\Branch\Traits\HasBranchCurrency;
use Modules\Discount\Database\Factories\DiscountFactory;
use Modules\Export\Contracts\ModelExportDefinitionInterface;
use Modules\Loyalty\Models\LoyaltyGift;
use Modules\Order\Facades\Pricing as FacadePricing;
use Modules\Order\Models\Order;
use Modules\Order\Pricing\Pricing;
use Modules\Support\Eloquent\Model;
use Modules\Support\Enums\PriceType;
use Modules\Support\Money;
use Modules\Support\Traits\HasActiveStatus;
use Modules\Support\Traits\HasCreatedBy;
use Modules\Support\Traits\HasFilters;
use Modules\Support\Traits\HasSortBy;
use Modules\Support\Traits\HasTagsCache;
use Modules\Translation\Traits\Translatable;

/**
 * @property int $id
 * @property string $name
 * @property string $description
 * @property PriceType $type
 * @property int|Money $value
 * @property Money|null $minimum_spend
 * @property Money|null $maximum_spend
 * @property Money|null $max_discount
 * @property array|null $conditions
 * @property int|null $usage_limit
 * @property int|null $per_customer_limit
 * @property array|null $meta
 * @property-read bool $used
 * @property-read LoyaltyGift|null $gift
 * @property Carbon|null $start_date
 * @property Carbon|null $end_date
 * @property Carbon|null $updated_at
 * @property Carbon|null $created_at
 * @property Carbon|null $deleted_at
 */
class Discount extends Model implements ModelExportDefinitionInterface
{
    use HasActiveStatus,
        HasActivityLog,
        HasBranch,
        HasBranchCurrency,
        HasCreatedBy,
        HasFactory,
        HasFilters,
        HasSortBy,
        HasTagsCache,
        SoftDeletes,
        Translatable;

    /**
     * The attributes that are mass assignable.
     */
    protected $fillable = [
        'name',
        'description',
        'value',
        'type',
        'conditions',
        'minimum_spend',
        'maximum_spend',
        'max_discount',
        'usage_limit',
        'per_customer_limit',
        'meta',
        'start_date',
        'end_date',
        'ends_at',
        self::ACTIVE_COLUMN_NAME,
        self::BRANCH_COLUMN_NAME,
    ];

    /**
     * The attributes that are translatable.
     */
    protected array $translatable = ['name', 'description'];

    /** {@inheritDoc} */
    public static function exportColumns(): array
    {
        return [
            ['path' => 'id', 'label' => 'admin::admin.table.id'],
            ['path' => 'name', 'label' => 'discount::discounts.table.name'],
            ['path' => 'description', 'label' => 'discount::discounts.table.description'],
            ['path' => 'branch.name', 'label' => 'branch::branches.table.branch', 'with' => ['branch:id,name']],
            ['path' => 'type', 'label' => 'discount::discounts.table.type'],
            ['path' => 'value', 'label' => 'discount::discounts.table.value_text'],
            ['path' => 'minimum_spend', 'label' => 'discount::discounts.table.minimum_spend', 'type' => 'money'],
            ['path' => 'maximum_spend', 'label' => 'discount::discounts.table.maximum_spend', 'type' => 'money'],
            ['path' => 'max_discount', 'label' => 'discount::discounts.table.max_discount', 'type' => 'money'],
            ['path' => 'usage_limit', 'label' => 'discount::discounts.table.usage_limit'],
            ['path' => 'per_customer_limit', 'label' => 'discount::discounts.table.per_customer_limit'],
            ['path' => 'used', 'label' => 'discount::discounts.table.used'],
            ['path' => 'is_active', 'label' => 'admin::admin.table.activation', 'type' => 'boolean'],
            ['path' => 'start_date', 'label' => 'discount::discounts.table.start_date', 'type' => 'date'],
            ['path' => 'end_date', 'label' => 'discount::discounts.table.end_date', 'type' => 'date'],
            ['path' => 'createdBy.name', 'label' => 'admin::admin.table.created_by', 'with' => ['createdBy:id,name']],
            ['path' => 'created_at', 'label' => 'admin::admin.table.created_at', 'type' => 'datetime'],
            ['path' => 'updated_at', 'label' => 'admin::admin.table.updated_at', 'type' => 'datetime'],
        ];
    }

    /**
     * Get a list of all discounts.
     */
    public static function list(?int $branchId = null): Collection
    {
        $discounts = Cache::tags('discounts')
            ->rememberForever(
                makeCacheKey([
                    'discounts',
                    is_null($branchId) ? 'all' : "branch-$branchId",
                    'list',
                ]),
                fn () => static::select(
                    'id',
                    'name',
                    'value',
                    'start_date',
                    'end_date',
                    'type',
                )
                    ->whereNull('meta->reward_id')
                    ->latest()
                    ->when($branchId !== null, fn ($q) => $q
                        ->withOutGlobalBranchPermission()
                        ->where(function ($q) use ($branchId) {
                            $q->where('branch_id', $branchId)
                                ->orWhereNull('branch_id');
                        }))
                    ->get()
            );

        return $discounts
            ->filter(fn (Discount $discount) => $discount->valid())
            ->map(fn (Discount $discount) => [
                'id' => $discount->id,
                'name' => "$discount->name ({$discount->getValueText()})",
            ])
            ->values();
    }

    /**
     * Determine if discount is valid
     */
    public function valid(): bool
    {
        if ($this->hasStartDate() && $this->hasEndDate()) {
            return $this->startDateIsValid() && $this->endDateIsValid();
        }

        if ($this->hasStartDate()) {
            return $this->startDateIsValid();
        }

        if ($this->hasEndDate()) {
            return $this->endDateIsValid();
        }

        return true;
    }

    /**
     * Determine if it has a start date
     */
    private function hasStartDate(): bool
    {
        return ! is_null($this->start_date);
    }

    /**
     * Determine if it has an end date
     */
    private function hasEndDate(): bool
    {
        return ! is_null($this->end_date);
    }

    /**
     * Determine if it has a start date is valid
     */
    private function startDateIsValid(): bool
    {
        return today() >= $this->start_date;
    }

    /**
     * Determine if it has an end is valid
     */
    private function endDateIsValid(): bool
    {
        return today() <= $this->end_date;
    }

    /**
     * Get value text
     */
    public function getValueText(): string
    {
        return $this->type === PriceType::Fixed ? $this->value->format() : round($this->value, 3).'%';
    }

    /**
     * Boot the model and handle stock adjustments on create, update, and delete.
     */
    protected static function booted(): void
    {
        static::saving(function (Discount $discount) {
            if ($discount->type === PriceType::Fixed) {
                $discount->max_discount = null;
            }
        });
    }

    protected static function newFactory(): DiscountFactory
    {
        return DiscountFactory::new();
    }

    /** {@inheritDoc} */
    public function allowedFilterKeys(): array
    {
        return [
            'search',
            'from',
            'to',
            'type',
            self::ACTIVE_COLUMN_NAME,
            self::BRANCH_COLUMN_NAME,
        ];
    }

    /**
     * Get minimum spend
     *
     * @return Attribute<Money|null>
     */
    public function minimumSpend(): Attribute
    {
        return Attribute::get(
            fn ($minimumSpend) => ! is_null($minimumSpend)
                ? Money::inDefaultCurrency($minimumSpend)
                : null
        );
    }

    /**
     * Get minimum spend
     *
     * @return Attribute<Money|null>
     */
    public function maximumSpend(): Attribute
    {
        return Attribute::get(
            fn ($maximumSpend) => ! is_null($maximumSpend)
                ? Money::inDefaultCurrency($maximumSpend)
                : null
        );
    }

    /**
     * Get max discount
     *
     * @return Attribute<Money|null>
     */
    public function maxDiscount(): Attribute
    {
        return Attribute::get(
            fn ($maxDiscount) => ! is_null($maxDiscount)
                ? Money::inDefaultCurrency($maxDiscount)
                : null
        );
    }

    /**
     * Get value
     *
     * @return Attribute<Money|float>
     */
    public function value(): Attribute
    {
        return Attribute::get(
            fn ($value) => $this->type === PriceType::Fixed
                ? Money::inDefaultCurrency($value)
                : $value
        );
    }

    /**
     * Determine if discount invalid
     */
    public function invalid(): bool
    {
        return ! $this->valid();
    }

    /**
     * Did not spend the required amount
     */
    public function didNotSpendTheRequiredAmount(Pricing|FacadePricing $cart): bool
    {
        if (is_null($this->minimum_spend)) {
            return false;
        }

        $branch = $cart instanceof Pricing ? $cart->branch() : $cart::branch();

        if (is_null($branch)) {
            return true;
        }

        $minimumSpend = $this->minimum_spend->convert($branch->currency());

        return $cart instanceof Pricing
            ? $cart->subTotal()->lessThan($minimumSpend)
            : $cart::subTotal()->lessThan($minimumSpend);
    }

    /**
     * Spent more than a maximum amount
     */
    public function spentMoreThanMaximumAmount(Pricing|FacadePricing $cart): bool
    {
        if (is_null($this->maximum_spend)) {
            return false;
        }

        $branch = $cart instanceof Pricing ? $cart->branch() : $cart::branch();

        if (is_null($branch)) {
            return true;
        }

        $maximumSpend = $this->maximum_spend->convert($branch->currency());

        return $cart instanceof Pricing
            ? $cart->subTotal()->greaterThan($maximumSpend)
            : $cart::subTotal()->greaterThan($maximumSpend);
    }

    /**
     * Usage limit reached
     */
    public function usageLimitReached(?int $userId = null, ?int $exceptOrderId = null): bool
    {
        return $this->perDiscountUsageLimitReached($exceptOrderId) || $this->perCustomerUsageLimitReached($userId, $exceptOrderId);
    }

    /**
     * per discount usage limit reached
     */
    public function perDiscountUsageLimitReached(?int $exceptOrderId = null): bool
    {
        if (is_null($this->usage_limit)) {
            return false;
        }

        return ($this->used - (! is_null($exceptOrderId) ? 1 : 0)) >= $this->usage_limit;
    }

    /**
     * Per customer usage limit reached
     */
    public function perCustomerUsageLimitReached(?int $userId = null, ?int $exceptOrderId = null): bool
    {
        if (is_null($userId) || $this->discountHasNoUsageLimitForCustomers()) {
            return false;
        }

        $used = Order::where('customer_id', $userId)
            ->whereHas(
                'discount',
                fn ($query) => $query->where('discountable_type', $this->getMorphClass())
                    ->where('discountable_id', $this->id)
            )
            ->when(! is_null($exceptOrderId), fn ($query) => $query->where('id', '<>', $exceptOrderId))
            ->count();

        return $used >= $this->per_customer_limit;
    }

    /**
     * Discount has no usage limit for customers
     */
    private function discountHasNoUsageLimitForCustomers(): bool
    {
        return is_null($this->per_customer_limit);
    }

    /**
     * Mark this discount as used once.
     */
    public function usedOnce(): void
    {
        if ($this->exists) {
            $this->increment('used');
        }
    }

    /**
     * Decrease the usage count by one.
     */
    public function unusedOnce(): void
    {
        if ($this->exists && $this->used > 0) {
            $this->decrement('used');
        }
    }

    /**
     * Get gift
     */
    public function gift(): MorphOne
    {
        return $this->morphOne(LoyaltyGift::class, 'artifact');
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'type' => PriceType::class,
            self::ACTIVE_COLUMN_NAME => 'boolean',
            'conditions' => 'array',
            'meta' => 'array',
            'usage_limit' => 'int',
            'per_customer_limit' => 'int',
            'start_date' => 'date',
            'end_date' => 'date',
        ];
    }

    /** {@inheritDoc} */
    protected function getSortableAttributes(): array
    {
        return [
            'name',
            'value',
            'type',
            'starts_at',
            'ends_at',
            self::ACTIVE_COLUMN_NAME,
            self::BRANCH_COLUMN_NAME,
        ];
    }
}
