<?php

namespace Modules\Inventory\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Modules\ActivityLog\Traits\HasActivityLog;
use Modules\Branch\Traits\HasBranch;
use Modules\Branch\Traits\HasBranchCurrency;
use Modules\Export\Contracts\ModelExportDefinitionInterface;
use Modules\Inventory\Database\Factories\IngredientFactory;
use Modules\Support\Eloquent\Model;
use Modules\Support\Money;
use Modules\Support\Traits\HasCreatedBy;
use Modules\Support\Traits\HasFilters;
use Modules\Support\Traits\HasSortBy;
use Modules\Support\Traits\HasTagsCache;
use Modules\Translation\Traits\Translatable;
use Modules\User\Models\User;

/**
 * @property int $id
 * @property string $name
 * @property int $unit_id
 * @property Unit $unit
 * @property float $cost_per_unit
 * @property float $alert_quantity
 * @property float $current_stock
 * @property float $is_returnable
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property Carbon|null $deleted_at
 */
class Ingredient extends Model implements ModelExportDefinitionInterface
{
    use HasActivityLog,
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
        'unit_id',
        'cost_per_unit',
        'alert_quantity',
        'current_stock',
        'is_returnable',
        self::BRANCH_COLUMN_NAME,
    ];

    /**
     * The attributes that are translatable.
     */
    protected array $translatable = ['name'];

    /**
     * The relations to eager load on every query.
     *
     * @var array
     */
    protected $with = ['unit'];

    /**
     * Ordered export column definitions.
     *
     * @return array<int,array<string,mixed>>
     */
    public static function exportColumns(): array
    {
        return [
            ['path' => 'id', 'label' => 'admin::admin.table.id'],
            ['path' => 'name', 'label' => 'inventory::ingredients.table.name'],
            ['path' => 'branch.name', 'label' => 'branch::branches.table.branch', 'with' => ['branch:id,name,currency']],
            ['path' => 'currency', 'label' => 'inventory::ingredients.table.currency'],
            ['path' => 'unit.name', 'label' => 'inventory::ingredients.table.unit', 'with' => ['unit:id,name,symbol']],
            ['path' => 'unit.symbol', 'label' => 'inventory::ingredients.table.unit_symbol', 'with' => ['unit:id,name,symbol']],
            ['path' => 'cost_per_unit', 'label' => 'inventory::ingredients.table.cost_per_unit', 'type' => 'money'],
            ['path' => 'alert_quantity', 'label' => 'inventory::ingredients.table.alert_quantity'],
            ['path' => 'current_stock', 'label' => 'inventory::ingredients.table.current_stock'],
            ['path' => 'is_returnable', 'label' => 'inventory::ingredients.table.is_returnable', 'type' => 'boolean'],
            ['path' => 'createdBy.name', 'label' => 'admin::admin.table.created_by', 'with' => ['createdBy:id,name']],
            ['path' => 'created_at', 'label' => 'admin::admin.table.created_at', 'type' => 'datetime'],
            ['path' => 'updated_at', 'label' => 'admin::admin.table.updated_at', 'type' => 'datetime'],
        ];
    }

    /**
     * Get a list of all ingredients.
     */
    public static function list(
        ?int $branchId = null,
        bool $withoutGlobalBranchPermission = false,
        ?string $search = null,
        ?int $limit = null,
        array $ids = [],
        ?int $unitId = null,
    ): Collection {
        $ids = array_values(array_unique(array_map('intval', $ids)));
        sort($ids);
        $search = filled($search) ? trim((string) $search) : null;
        $limit = ! is_null($limit) ? max(1, min($limit, 100)) : null;
        $user = auth()->user();
        $permissionBranchId = ! $withoutGlobalBranchPermission
        && $user instanceof User
        && $user->assignedToBranch()
            ? $user->branch_id
            : null;

        return Cache::tags(['ingredients', 'units'])->rememberForever(
            makeCacheKey([
                'ingredients',
                is_null($branchId) ? 'branch-all' : "branch-$branchId",
                $withoutGlobalBranchPermission ? 'scope-unrestricted' : 'scope-restricted',
                is_null($permissionBranchId) ? 'permission-branch-all' : "permission-branch-$permissionBranchId",
                is_null($search) ? 'search-none' : "search-$search",
                is_null($limit) ? 'limit-all' : "limit-$limit",
                $ids === [] ? 'ids-all' : 'ids-'.implode(',', $ids),
                is_null($unitId) ? 'unit-all' : "unit-$unitId",
                'list',
            ]),
            fn () => static::query()
                ->when($withoutGlobalBranchPermission, fn (Builder $query) => $query->withOutGlobalBranchPermission())
                ->select('id', 'name', 'unit_id', 'current_stock')
                ->with('unit:id,name,symbol')
                ->when(! is_null($branchId), fn ($query) => $query->whereBranch($branchId))
                ->when($ids !== [], fn (Builder $query) => $query->whereIn('id', $ids))
                ->when($ids === [] && filled($search), fn (Builder $query) => $query->search($search))
                ->when(! is_null($unitId), fn (Builder $query) => $query->where('unit_id', $unitId))
                ->orderBy('id')
                ->when(! is_null($limit), fn (Builder $query) => $query->limit($limit))
                ->get()
                ->map(fn (Ingredient $ingredient) => [
                    'id' => $ingredient->id,
                    'name' => $ingredient->name,
                    'unit_id' => $ingredient->unit_id,
                    'unit' => $ingredient->unit?->symbol,
                    'symbol' => $ingredient->unit?->symbol ? ucfirst($ingredient->unit->symbol) : null,
                    'current_stock' => $ingredient->current_stock,
                ])
                ->values(),
        );
    }

    protected static function newFactory(): IngredientFactory
    {
        return IngredientFactory::new();
    }

    /** {@inheritDoc} */
    public function allowedFilterKeys(): array
    {
        return [
            'search',
            'from',
            'to',
            'unit_id',
            self::BRANCH_COLUMN_NAME,
        ];
    }

    /**
     * Scope a query to search across all fields.
     */
    public function scopeSearch(Builder $query, string $value): void
    {
        $query->whereLikeTranslation('name', $value);
    }

    /**
     * Get a unit model
     */
    public function unit(): BelongsTo
    {
        return $this->belongsTo(Unit::class)
            ->withTrashed();
    }

    /**
     * Get cost per unit
     */
    public function costPerUnit(): Attribute
    {
        return Attribute::get(fn ($costPerUnit) => new Money($costPerUnit, $this->currency));
    }

    /** {@inheritDoc} */
    protected function getSortableAttributes(): array
    {
        return [
            'name',
            'cost_per_unit',
            'alert_quantity',
            'current_stock',
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
            'alert_quantity' => 'float',
            'current_stock' => 'float',
            'is_returnable' => 'boolean',
        ];
    }
}
