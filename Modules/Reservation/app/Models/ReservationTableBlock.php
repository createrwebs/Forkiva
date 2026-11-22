<?php

namespace Modules\Reservation\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Modules\ActivityLog\Traits\HasActivityLog;
use Modules\Branch\Traits\HasBranch;
use Modules\Export\Contracts\ModelExportDefinitionInterface;
use Modules\Reservation\Database\Factories\ReservationTableBlockFactory;
use Modules\SeatingPlan\Models\Table;
use Modules\Support\Eloquent\Model;
use Modules\Support\Traits\HasCreatedBy;
use Modules\Support\Traits\HasFilters;
use Modules\Support\Traits\HasSortBy;
use Modules\Translation\Traits\Translatable;
use Modules\User\Models\User;

/**
 * @property int $id
 * @property int $branch_id
 * @property int|null $table_id
 * @property array|string $title
 * @property string|null $reason
 * @property Carbon $start_at
 * @property Carbon $end_at
 * @property int|null $created_by
 * @property-read Table|null $table
 * @property-read User|null $created_at
 * @property-read User|null $updated_at
 */
class ReservationTableBlock extends Model implements ModelExportDefinitionInterface
{
    use HasActivityLog,
        HasBranch,
        HasCreatedBy,
        HasFactory,
        HasFilters,
        HasSortBy,
        SoftDeletes,
        Translatable;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'branch_id',
        'table_id',
        'title',
        'reason',
        'start_at',
        'end_at',
        'created_by',
    ];

    /**
     * The attributes that are translatable.
     *
     * @var array<int, string>
     */
    protected array $translatable = ['title'];

    /**
     * Ordered export column definitions.
     *
     * @return array<int, array<string, mixed>>
     */
    public static function exportColumns(): array
    {
        return [
            ['path' => 'branch.name', 'label' => 'branch::branches.table.branch', 'with' => ['branch:id,name']],
            ['path' => 'table.name', 'label' => 'reservation::reservation_table_blocks.table.table', 'with' => ['table:id,name,floor_id,zone_id']],
            ['path' => 'table.floor.name', 'label' => 'seatingplan::tables.table_columns.floor', 'with' => ['table.floor:id,name']],
            ['path' => 'table.zone.name', 'label' => 'seatingplan::tables.table_columns.zone', 'with' => ['table.zone:id,name']],
            ['path' => 'title', 'label' => 'reservation::reservation_table_blocks.table.title'],
            ['path' => 'start_at', 'label' => 'reservation::reservation_table_blocks.table.start_at', 'type' => 'datetime'],
            ['path' => 'end_at', 'label' => 'reservation::reservation_table_blocks.table.end_at', 'type' => 'datetime'],
            ['path' => 'createdBy.name', 'label' => 'admin::admin.table.created_by', 'with' => ['createdBy:id,name']],
        ];
    }

    /**
     * Create a new factory instance for the model.
     */
    protected static function newFactory(): ReservationTableBlockFactory
    {
        return ReservationTableBlockFactory::new();
    }

    /**
     * Get blocked table, if any.
     */
    public function table(): BelongsTo
    {
        return $this->belongsTo(Table::class)
            ->withOutGlobalBranchPermission()
            ->withoutGlobalActive()
            ->withTrashed();
    }

    /**
     * Get allowed filter keys.
     *
     * @return list<string>
     */
    public function allowedFilterKeys(): array
    {
        return ['search', 'branch_id', 'table_id', 'created_by', 'from', 'to'];
    }

    /**
     * Scope query by search text.
     */
    public function scopeSearch(Builder $query, string $value): void
    {
        $query->whereLikeTranslation('title', $value)
            ->orWhere('reason', 'like', "%$value%");
    }

    /**
     * Get sortable attributes.
     *
     * @return array<int, string>
     */
    protected function getSortableAttributes(): array
    {
        return [
            'branch_id',
            'table_id',
            'title',
            'created_by',
            'start_at',
            'end_at',
            'created_at',
        ];
    }

    /**
     * Get model casts.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'start_at' => 'datetime',
            'end_at' => 'datetime',
        ];
    }
}
