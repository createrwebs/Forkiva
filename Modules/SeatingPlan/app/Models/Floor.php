<?php

namespace Modules\SeatingPlan\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Modules\ActivityLog\Traits\HasActivityLog;
use Modules\Branch\Traits\HasBranch;
use Modules\Export\Contracts\ModelExportDefinitionInterface;
use Modules\SeatingPlan\Database\Factories\FloorFactory;
use Modules\Support\Eloquent\Model;
use Modules\Support\Traits\HasActiveStatus;
use Modules\Support\Traits\HasCreatedBy;
use Modules\Support\Traits\HasFilters;
use Modules\Support\Traits\HasOrder;
use Modules\Support\Traits\HasSortBy;
use Modules\Support\Traits\HasTagsCache;
use Modules\Translation\Traits\Translatable;

/**
 * @property int $id
 * @property string $name
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property Carbon|null $deleted_at
 */
class Floor extends Model implements ModelExportDefinitionInterface
{
    use HasActiveStatus,
        HasActivityLog,
        HasBranch,
        HasCreatedBy,
        HasFactory,
        HasFilters,
        HasOrder,
        HasSortBy,
        HasTagsCache,
        SoftDeletes,
        Translatable;

    /**
     * The attributes that are mass assignable.
     */
    protected $fillable = [
        'name',
        self::ACTIVE_COLUMN_NAME,
        self::BRANCH_COLUMN_NAME,
        self::ORDER_COLUMN_NAME,
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
            ['path' => 'name', 'label' => 'seatingplan::floors.table.name'],
            ['path' => 'branch.name', 'label' => 'branch::branches.table.branch', 'with' => ['branch:id,name']],
            ['path' => 'is_active', 'label' => 'admin::admin.table.activation', 'type' => 'boolean'],
            ['path' => 'createdBy.name', 'label' => 'admin::admin.table.created_by', 'with' => ['createdBy:id,name']],
            ['path' => 'created_at', 'label' => 'admin::admin.table.created_at', 'type' => 'datetime'],
            ['path' => 'updated_at', 'label' => 'admin::admin.table.updated_at', 'type' => 'datetime'],
        ];
    }

    /**
     * Get a list of all floors.
     */
    public static function list(?int $branchId = null): Collection
    {
        return Cache::tags('floors')
            ->rememberForever(
                makeCacheKey([
                    'floors',
                    is_null($branchId) ? 'all' : "branch-{$branchId}",
                    'list',
                ]),
                fn () => static::select('id', 'name')
                    ->when(! is_null($branchId), fn ($query) => $query->whereBranch($branchId))
                    ->get()
                    ->map(fn (Floor $floor) => [
                        'id' => $floor->id,
                        'name' => $floor->name,
                    ])
            );
    }

    protected static function newFactory(): FloorFactory
    {
        return FloorFactory::new();
    }

    /** {@inheritDoc} */
    public function allowedFilterKeys(): array
    {
        return [
            'search',
            'from',
            'to',
            self::ACTIVE_COLUMN_NAME,
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
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            self::ACTIVE_COLUMN_NAME => 'boolean',
            self::ORDER_COLUMN_NAME => 'int',
        ];
    }

    /** {@inheritDoc} */
    protected function getSortableAttributes(): array
    {
        return [
            'name',
            self::ACTIVE_COLUMN_NAME,
            self::BRANCH_COLUMN_NAME,
        ];
    }
}
