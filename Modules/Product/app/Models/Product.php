<?php

namespace Modules\Product\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Modules\ActivityLog\Traits\HasActivityLog;
use Modules\Branch\Models\Branch;
use Modules\Branch\Traits\HasBranchCurrency;
use Modules\Category\Models\Category;
use Modules\Export\Contracts\ModelExportDefinitionInterface;
use Modules\Inventory\Models\Ingredient;
use Modules\Media\Models\Media;
use Modules\Media\Traits\HasMedia;
use Modules\Menu\Models\Menu;
use Modules\Menu\Traits\HasMenu;
use Modules\Option\Models\Option;
use Modules\Product\Database\Factories\ProductFactory;
use Modules\Product\Enums\ProductInventoryConsumptionMode;
use Modules\Product\Traits\HasSpecialPrice;
use Modules\Product\Traits\IsNew;
use Modules\Support\Eloquent\Model;
use Modules\Support\Enums\PriceType;
use Modules\Support\Money;
use Modules\Support\Traits\HasActiveStatus;
use Modules\Support\Traits\HasCreatedBy;
use Modules\Support\Traits\HasFilters;
use Modules\Support\Traits\HasSortBy;
use Modules\Support\Traits\HasTagsCache;
use Modules\Tax\Models\Tax;
use Modules\Translation\Traits\Translatable;
use Znck\Eloquent\Traits\BelongsToThrough;

/**
 * @property int $id
 * @property string $sku
 * @property string $name
 * @property string $description
 * @property Collection<Tax> $taxes
 * @property ProductInventoryConsumptionMode $inventory_consumption_mode
 * @property int|null $produced_ingredient_id
 * @property string|null $produced_ingredient_quantity
 * @property-read  Tax $tax
 * @property Money $price
 * @property int $prep_time_minutes
 * @property Money $selling_price
 * @property-read Media|null $thumbnail
 * @property-read string $currency
 * @property-read Branch $branch
 * @property-read Collection<Category> $categories
 * @property-read Collection<Ingredientable> $ingredients
 * @property-read Ingredient|null $producedIngredient
 * @property-read Collection<Option> $options
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property Carbon|null $deleted_at
 */
class Product extends Model implements ModelExportDefinitionInterface
{
    use BelongsToThrough,
        HasActiveStatus,
        HasActivityLog,
        HasBranchCurrency,
        HasCreatedBy,
        HasFactory,
        HasFilters,
        HasMedia,
        HasMenu,
        HasSortBy,
        HasSpecialPrice,
        HasTagsCache,
        IsNew,
        SoftDeletes,
        Translatable;

    /**
     * The attributes that are mass assignable.
     */
    protected $fillable = [
        'name',
        'description',
        'price',
        'prep_time_minutes',
        'special_price',
        'special_price_type',
        'special_price_start',
        'special_price_end',
        'new_from',
        'new_to',
        'sku',
        'inventory_consumption_mode',
        'produced_ingredient_id',
        'produced_ingredient_quantity',
        self::ACTIVE_COLUMN_NAME,
        self::MENU_COLUMN_NAME,
    ];

    /**
     * The relations to eager load on every query.
     *
     * @var array
     */
    protected $with = ['branch:branches.id,branches.currency'];

    /**
     * The attributes that are translatable.
     */
    protected array $translatable = ['name', 'description'];

    /**
     * Ordered export column definitions.
     *
     * @return array<int,array<string,mixed>>
     */
    public static function exportColumns(): array
    {
        return [
            ['path' => 'id', 'label' => 'admin::admin.table.id'],
            ['path' => 'sku', 'label' => 'product::products.table.sku'],
            ['path' => 'name', 'label' => 'product::products.table.name'],
            ['path' => 'description', 'label' => 'product::products.table.description'],
            ['path' => 'menu.name', 'label' => 'product::products.table.menu', 'with' => ['menu:id,name']],
            ['path' => 'branch.name', 'label' => 'branch::branches.table.branch', 'with' => ['branch:branches.id,branches.name,branches.currency']],
            ['path' => 'currency', 'label' => 'product::products.table.currency'],
            ['path' => 'price', 'label' => 'product::products.table.price', 'type' => 'money'],
            ['path' => 'special_price', 'label' => 'product::products.table.special_price', 'type' => 'money'],
            ['path' => 'special_price_type', 'label' => 'product::products.table.special_price_type'],
            ['path' => 'selling_price', 'label' => 'product::products.table.selling_price', 'type' => 'money'],
            ['path' => 'special_price_start', 'label' => 'product::products.table.special_price_start', 'type' => 'datetime'],
            ['path' => 'special_price_end', 'label' => 'product::products.table.special_price_end', 'type' => 'datetime'],
            ['path' => 'new_from', 'label' => 'product::products.table.new_from', 'type' => 'datetime'],
            ['path' => 'new_to', 'label' => 'product::products.table.new_to', 'type' => 'datetime'],
            ['path' => 'categories_count', 'label' => 'product::products.table.categories_count', 'with_count' => 'categories'],
            ['path' => 'taxes_count', 'label' => 'product::products.table.taxes_count', 'with_count' => 'taxes'],
            ['path' => 'is_active', 'label' => 'admin::admin.table.activation', 'type' => 'boolean'],
            ['path' => 'createdBy.name', 'label' => 'admin::admin.table.created_by', 'with' => ['createdBy:id,name']],
            ['path' => 'created_at', 'label' => 'admin::admin.table.created_at', 'type' => 'datetime'],
            ['path' => 'updated_at', 'label' => 'admin::admin.table.updated_at', 'type' => 'datetime'],
        ];
    }

    /**
     * Get a list of all products group by sku.
     */
    public static function listBySku(): Collection
    {
        return Cache::tags('products')
            ->rememberForever(
                makeCacheKey(['products', 'sku', 'list']),
                fn () => static::select('id', 'name', 'sku')
                    ->without(['branch'])
                    ->whereNotNull('sku')
                    ->groupBy('sku')
                    ->get()
                    ->map(fn (Product $product) => [
                        'id' => $product->sku,
                        'name' => $product->name,
                    ])
            );
    }

    protected static function newFactory(): ProductFactory
    {
        return ProductFactory::new();
    }

    /**
     * Get the categories associated with the product.
     *
     * @return BelongsToMany<Category>
     */
    public function categories(): BelongsToMany
    {
        return $this->belongsToMany(Category::class, 'product_categories');
    }

    /**
     * Get the taxes configuration assigned to the product.
     *
     * @return BelongsToMany<Tax>
     */
    public function taxes(): BelongsToMany
    {
        return $this->belongsToMany(Tax::class, 'product_taxes')
            ->withOutGlobalBranchPermission()
            ->withoutGlobalActive();
    }

    /** {@inheritDoc} */
    public function allowedFilterKeys(): array
    {
        return [
            'search',
            'from',
            'to',
            self::ACTIVE_COLUMN_NAME,
            self::MENU_COLUMN_NAME,
        ];
    }

    /**
     * Scope a query to search across all fields.
     */
    public function scopeSearch(Builder $query, string $value): void
    {
        $query->where(fn (Builder $query) => $query->whereLikeTranslation('name', $value)->orLike('sku', $value));
    }

    /**
     * Get selling price
     *
     * @return Attribute<Money>
     */
    public function sellingPrice(): Attribute
    {
        return Attribute::get(fn () => ($this->hasSpecialPrice() ? $this->getSpecialPrice() : $this->price));
    }

    /**
     * Get product thumbnail
     *
     * @return Attribute<Media|null>
     */
    public function thumbnail(): Attribute
    {
        return Attribute::get(fn () => $this->relationLoaded('files') ? $this->files->where('pivot.zone', 'thumbnail')->first() : null);
    }

    /**
     * Get branch
     */
    public function branch(): \Znck\Eloquent\Relations\BelongsToThrough
    {
        return $this->belongsToThrough(Branch::class, Menu::class);
    }

    /**
     * Get currency
     */
    public function currency(): Attribute
    {
        return Attribute::get(
            fn () => $this->relationLoaded('branch')
                ? ($this->branch?->currency ?: setting('default_currency'))
                : setting('default_currency')
        );
    }

    /**
     * Get price
     */
    public function price(): Attribute
    {
        return Attribute::get(fn ($price) => new Money($price, $this->currency));
    }

    /**
     * Get options
     */
    public function options(): BelongsToMany
    {
        return $this->belongsToMany(Option::class, 'product_options')
            ->orderBy('order')
            ->with('values')
            ->withTrashed();
    }

    /**
     * Has any option
     */
    public function hasAnyOption(): bool
    {
        return $this->getAttribute('options')->isNotEmpty();
    }

    /**
     * Get ingredients
     */
    public function ingredients(): MorphMany
    {
        return $this->morphMany(Ingredientable::class, 'ingredientable')
            ->orderBy('order');
    }

    /**
     * Get the prepared inventory item consumed when this product is sold.
     */
    public function producedIngredient(): BelongsTo
    {
        return $this->belongsTo(Ingredient::class, 'produced_ingredient_id')
            ->withOutGlobalBranchPermission();
    }

    /** {@inheritDoc} */
    protected function getSortableAttributes(): array
    {
        return [
            'name',
            'price',
            self::ACTIVE_COLUMN_NAME,
        ];
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'inventory_consumption_mode' => ProductInventoryConsumptionMode::class,
            'produced_ingredient_quantity' => 'decimal:6',
            'special_price_type' => PriceType::class,
            'new_from' => 'datetime',
            'new_to' => 'datetime',
            'special_price_start' => 'datetime',
            'special_price_end' => 'datetime',
            self::ACTIVE_COLUMN_NAME => 'boolean',
        ];
    }
}
