<?php

namespace Modules\Category\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Kalnoy\Nestedset\NodeTrait;
use Modules\ActivityLog\Traits\HasActivityLog;
use Modules\Export\Contracts\ModelExportDefinitionInterface;
use Modules\Media\Models\Media;
use Modules\Media\Traits\HasMedia;
use Modules\Menu\Traits\HasMenu;
use Modules\Product\Models\Product;
use Modules\Support\Eloquent\Model;
use Modules\Support\Traits\HasActiveStatus;
use Modules\Support\Traits\HasCreatedBy;
use Modules\Support\Traits\HasFilters;
use Modules\Support\Traits\HasOrder;
use Modules\Support\Traits\HasTagsCacheWithoutBuilder;
use Modules\Translation\Traits\Translatable;

/**
 * @property int $id
 * @property string $name
 * @property string $slug
 * @property int|null $parent_id
 * @property-read Category|null $parent
 * @property-read Media|null $logo
 * @property-read Collection<Product> $products
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property Carbon|null $deleted_at
 */
class Category extends Model implements ModelExportDefinitionInterface
{
    use HasActiveStatus,
        HasActivityLog,
        HasCreatedBy,
        HasFilters,
        HasMedia,
        HasMenu,
        HasOrder,
        HasTagsCacheWithoutBuilder,
        NodeTrait,
        SoftDeletes,
        Translatable;

    /**
     * The attributes that are mass assignable.
     */
    protected $fillable = [
        'name',
        'parent_id',
        'slug',
        self::MENU_COLUMN_NAME,
        self::ACTIVE_COLUMN_NAME,
    ];

    /**
     * The attributes that are translatable.
     */
    protected array $translatable = ['name'];

    /**
     * Ordered export column definitions.
     *
     * @return array<int,array<string,mixed>>
     */
    public static function exportColumns(): array
    {
        return [
            ['path' => 'id', 'label' => 'admin::admin.table.id'],
            ['path' => 'name', 'label' => 'category::categories.table.name'],
            ['path' => 'slug', 'label' => 'category::categories.table.slug'],
            ['path' => 'parent.name', 'label' => 'category::categories.table.parent', 'with' => ['parent:id,name']],
            ['path' => 'menu.name', 'label' => 'category::categories.table.menu', 'with' => ['menu:id,name,branch_id']],
            ['path' => 'menu.branch.name', 'label' => 'branch::branches.table.branch', 'with' => ['menu:id,name,branch_id', 'menu.branch:id,name']],
            ['path' => 'createdBy.name', 'label' => 'admin::admin.table.created_by', 'with' => ['createdBy:id,name']],
            ['path' => 'products_count', 'label' => 'category::categories.table.products_count', 'with_count' => 'products'],
            ['path' => 'is_active', 'label' => 'admin::admin.table.activation', 'type' => 'boolean'],
            ['path' => 'created_at', 'label' => 'admin::admin.table.created_at', 'type' => 'datetime'],
            ['path' => 'updated_at', 'label' => 'admin::admin.table.updated_at', 'type' => 'datetime'],
        ];
    }

    /**
     * Get a tree list of all categories.
     */
    public static function treeList(int $menuId): array
    {
        return Cache::tags('categories')->rememberForever(
            makeCacheKey(['categories', "menu-$menuId", 'tree_list']),
            function () use ($menuId) {
                static::shouldBeStrict(false);

                $tree = static::whereMenu($menuId)
                    ->defaultOrder()
                    ->with('children')
                    ->get()
                    ->toTree();

                $result = static::flattenTree($tree);

                static::shouldBeStrict();

                return $result;
            }
        );
    }

    protected static function flattenTree($nodes, string $prefix = '', string $identifier = 'id'): array
    {
        $result = [];

        foreach ($nodes as $node) {
            $result[] = [
                $identifier => $node->{$identifier},
                'name' => $prefix.$node->name,
            ];

            if ($node->children->isNotEmpty()) {
                $result = array_merge(
                    $result,
                    static::flattenTree($node->children, $prefix.'¦–– ', $identifier)
                );
            }
        }

        return $result;
    }

    /**
     * Get a tree list of all categories.
     */
    public static function treeListWithSlug(?int $menuId = null): array
    {
        return Cache::tags('categories')->rememberForever(
            makeCacheKey([
                'categories',
                $menuId ? "menu-$menuId" : 'menu-all',
                'tree_list_with_slug',
            ]),
            function () use ($menuId) {
                static::shouldBeStrict(false);

                $query = static::whereNotNull('slug');

                if ($menuId !== null) {
                    $query->whereMenu($menuId);
                }

                $tree = $query
                    ->defaultOrder()
                    ->with('children')
                    ->get()
                    ->unique('slug')
                    ->toTree();

                $result = static::flattenTree($tree, '', 'slug');

                static::shouldBeStrict();

                return $result;
            }
        );
    }

    /**
     * Get fully active category ids
     */
    public static function getFullyActiveCategoryIds(int $menuId): Collection
    {
        $allCategories = Category::select('id', 'parent_id', 'is_active')
            ->where('menu_id', $menuId)
            ->get();

        $categoryMap = $allCategories->keyBy('id')->map(fn ($cat) => [
            'id' => $cat->id,
            'parent_id' => $cat->parent_id,
            'is_active' => (bool) $cat->is_active,
        ]);

        $isValid = function ($categoryId) use (&$categoryMap): bool {
            $current = $categoryMap[$categoryId] ?? null;

            while ($current && $current['parent_id']) {
                $parent = $categoryMap[$current['parent_id']] ?? null;
                if (! $parent || ! $parent['is_active']) {
                    return false;
                }
                $current = $parent;
            }

            return true;
        };

        return $allCategories
            ->filter(fn ($cat) => $cat->is_active && $isValid($cat->id))
            ->pluck('id');
    }

    /**
     * Get a list of all root categories.
     */
    public static function listWithSlug(?int $menuId = null): Collection
    {
        return Cache::tags('categories')
            ->rememberForever(
                makeCacheKey([
                    'categories',
                    is_null($menuId) ? 'menu-all' : "menu-$menuId",
                    'list_with_slug',
                ]),
                fn () => static::select('id', 'slug', 'parent_id', 'name')
                    ->whereNull('parent_id')
                    ->whereNotNull('slug')
                    ->when(! is_null($menuId), fn ($query) => $query->whereMenu($menuId))
                    ->orderBy('order')
                    ->orderBy('id')
                    ->get()
                    ->unique('slug')
                    ->values()
                    ->map(fn ($cat) => [
                        'slug' => $cat->slug,
                        'name' => $cat->name,
                    ]));
    }

    /**
     * Determine if category is root
     */
    public function isRoot(): bool
    {
        return $this->exists && is_null($this->parent_id);
    }

    /** {@inheritDoc} */
    public function allowedFilterKeys(): array
    {
        return [
            'from',
            'to',
            'search',
            self::MENU_COLUMN_NAME,
            self::ACTIVE_COLUMN_NAME,
            'branch_id',
        ];
    }

    /**
     * Get category logo
     */
    public function logo(): Attribute
    {
        return Attribute::get(fn () => $this->relationLoaded('files') ? $this->files->where('pivot.zone', 'logo')->first() : null);
    }

    /**
     * Get parent category.
     */
    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id')
            ->withoutGlobalActive()
            ->withTrashed();
    }

    /**
     * Recursive children (unlimited levels)
     */
    public function childrenRecursive(): Builder|HasMany
    {
        return $this->children()->with(['childrenRecursive', 'files'])
            ->orderBy(self::ORDER_COLUMN_NAME);
    }

    /**
     * Get children
     */
    public function children(): HasMany
    {
        return $this->hasMany(Category::class, 'parent_id')
            ->orderBy(self::ORDER_COLUMN_NAME);
    }

    /**
     * Set Slug
     */
    public function slug(): Attribute
    {
        return Attribute::set(fn ($slug) => $slug ? str($slug)->slug()->toString() : null);
    }

    /**
     * Get the products associated with the category.
     *
     * @return BelongsToMany<Product>
     */
    public function products(): BelongsToMany
    {
        return $this->belongsToMany(Product::class, 'product_categories');
    }

    /**
     * Scope a query to search across all fields.
     */
    public function scopeSearch(Builder $query, string $value): void
    {
        $query->whereLikeTranslation('name', $value);
    }

    /**
     * Get by branch id
     */
    public function scopeBranchId(Builder $query, string $value): void
    {
        $query->whereHas('menu', fn ($query) => $query->where('branch_id', $value));
    }

    /**
     * Override nested set queries to ignore the active global scope; otherwise tree
     * maintenance fails when inserting/updating inactive nodes.
     */
    public function newNestedSetQuery($table = null)
    {
        $builder = $this->usesSoftDelete()
            ? $this->withTrashed()
            : $this->newQuery();

        return $this->applyNestedSetScope(
            $builder->withoutGlobalActive(),
            $table
        );
    }

    public function newScopedQuery($table = null)
    {
        return $this->applyNestedSetScope(
            $this->newQuery()->withoutGlobalActive(),
            $table
        );
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            self::ACTIVE_COLUMN_NAME => 'boolean',
            'parent_id' => 'integer',
        ];
    }
}
