<?php

namespace Modules\Menu\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Modules\ActivityLog\Traits\HasActivityLog;
use Modules\Branch\Traits\HasBranch;
use Modules\Category\Models\Category;
use Modules\Export\Contracts\ModelExportDefinitionInterface;
use Modules\Menu\Database\Factories\MenuFactory;
use Modules\Product\Models\Product;
use Modules\Support\Eloquent\Model;
use Modules\Support\Traits\HasActiveStatus;
use Modules\Support\Traits\HasCreatedBy;
use Modules\Support\Traits\HasFilters;
use Modules\Support\Traits\HasSortBy;
use Modules\Support\Traits\HasTagsCache;
use Modules\Support\Traits\HasUuid;
use Modules\Translation\Traits\Translatable;

/**
 * @property int $id
 * @property string $name
 * @property string|null $description
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property Carbon|null $deleted_at
 */
class Menu extends Model implements ModelExportDefinitionInterface
{
    use HasActiveStatus,
        HasActivityLog,
        HasBranch,
        HasCreatedBy,
        HasFactory,
        HasFilters,
        HasSortBy,
        HasTagsCache,
        HasUuid,
        SoftDeletes,
        Translatable;

    /**
     * The attributes that are mass assignable.
     */
    protected $fillable = [
        'name',
        'description',
        self::ACTIVE_COLUMN_NAME,
        self::BRANCH_COLUMN_NAME,
    ];

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
            ['path' => 'name', 'label' => 'menu::menus.table.name'],
            ['path' => 'description', 'label' => 'menu::menus.table.description'],
            ['path' => 'branch.name', 'label' => 'branch::branches.table.branch', 'with' => ['branch:id,name']],
            ['path' => 'createdBy.name', 'label' => 'admin::admin.table.created_by', 'with' => ['createdBy:id,name']],
            ['path' => 'categories_count', 'label' => 'menu::menus.table.categories_count', 'with_count' => 'categories'],
            ['path' => 'products_count', 'label' => 'menu::menus.table.products_count', 'with_count' => 'products'],
            ['path' => 'is_active', 'label' => 'admin::admin.table.activation', 'type' => 'boolean'],
            ['path' => 'created_at', 'label' => 'admin::admin.table.created_at', 'type' => 'datetime'],
            ['path' => 'updated_at', 'label' => 'admin::admin.table.updated_at', 'type' => 'datetime'],
        ];
    }

    /**
     * Get a list of all menus.
     */
    public static function list(?int $branchId = null, bool $withoutGlobalActive = false): Collection
    {
        return Cache::tags('menus')
            ->rememberForever(
                makeCacheKey([
                    'menus',
                    is_null($branchId) ? 'all' : "branch-{$branchId}",
                    $withoutGlobalActive ? 'all' : 'active',
                    'list',
                ]),
                fn () => static::select('id', 'name')
                    ->when(! is_null($branchId), fn ($query) => $query->whereBranch($branchId))
                    ->when($withoutGlobalActive, fn ($query) => $query->withoutGlobalActive())
                    ->orderBy('is_active', 'desc')
                    ->get()
                    ->map(fn (Menu $menu) => [
                        'id' => $menu->id,
                        'name' => $menu->name,
                    ])
            );
    }

    /**
     * Get active menu
     */
    public static function getActiveMenu(int $branchId, bool $withBranch = false): ?Menu
    {
        return static::where('branch_id', $branchId)
            ->when($withBranch, fn (Builder $query) => $query->with(['branch']))
            ->where('is_active', true)
            ->withOutGlobalBranchPermission()
            ->latest()
            ->first();
    }

    protected static function newFactory(): MenuFactory
    {
        return MenuFactory::new();
    }

    /**
     * Perform any actions required after the model boots.
     */
    protected static function booted(): void
    {
        static::saved(fn (Menu $menu) => $menu->syncActiveMenu());
    }

    /**
     * Sync active agent
     */
    public function syncActiveMenu(): void
    {
        $this->withoutEvents(function () {
            $branchId = $this->branch_id;
            if ($this->is_active) {
                self::withoutGlobalActive()
                    ->withOutGlobalBranchPermission()
                    ->where('id', '<>', $this->id)
                    ->where('branch_id', $branchId)
                    ->update(['is_active' => false]);
            } elseif (! static::hasActiveMenu($this->id, $branchId)) {
                self::withoutGlobalActive()
                    ->withOutGlobalBranchPermission()
                    ->where('id', $this->id)
                    ->where('branch_id', $branchId)
                    ->update(['is_active' => true]);
            }
        });
    }

    /**
     * Get menu categories.
     */
    public function categories(): HasMany
    {
        return $this->hasMany(Category::class, 'menu_id');
    }

    /**
     * Get menu products.
     */
    public function products(): HasMany
    {
        return $this->hasMany(Product::class, 'menu_id');
    }

    /**
     * Determine id has active menu
     */
    public static function hasActiveMenu(?int $exceptionId = null, ?int $branchId = null): bool
    {
        return static::withoutGlobalActive()
            ->withOutGlobalBranchPermission()
            ->where(function ($query) use ($exceptionId) {
                $query->where('id', '<>', $exceptionId);
            })
            ->where('branch_id', $branchId)
            ->where('is_active', true)
            ->exists();
    }

    /** {@inheritDoc} */
    public function allowedFilterKeys(): array
    {
        return [
            'search',
            'from',
            'to',
            self::BRANCH_COLUMN_NAME,
            self::ACTIVE_COLUMN_NAME,
        ];
    }

    /**
     * Scope a query to search across all fields.
     */
    public function scopeSearch(Builder $query, string $value): void
    {
        $query->whereLikeTranslation('name', $value);
    }

    /** {@inheritDoc} */
    protected function getSortableAttributes(): array
    {
        return [
            'name',
            self::BRANCH_COLUMN_NAME,
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
            self::ACTIVE_COLUMN_NAME => 'boolean',
        ];
    }
}
