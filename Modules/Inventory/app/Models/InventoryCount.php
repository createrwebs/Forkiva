<?php

namespace Modules\Inventory\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Modules\ActivityLog\Traits\HasActivityLog;
use Modules\Branch\Traits\HasBranch;
use Modules\Export\Contracts\ModelExportDefinitionInterface;
use Modules\Inventory\Enums\InventoryCountStatus;
use Modules\Support\Eloquent\Model;
use Modules\Support\Traits\HasCreatedBy;
use Modules\Support\Traits\HasFilters;
use Modules\Support\Traits\HasSortBy;
use Modules\User\Models\User;

/**
 * @property int $id
 * @property string|null $reference_number
 * @property Carbon $count_date
 * @property InventoryCountStatus $status
 * @property string|null $notes
 * @property int|null $approved_by
 * @property Carbon|null $approved_at
 * @property int|null $cancelled_by
 * @property Carbon|null $cancelled_at
 * @property string|null $cancellation_reason
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property Carbon|null $deleted_at
 * @property-read Collection<int, InventoryCountItem> $items
 * @property-read User|null $approvedBy
 * @property-read User|null $cancelledBy
 */
class InventoryCount extends Model implements ModelExportDefinitionInterface
{
    use HasActivityLog,
        HasBranch,
        HasCreatedBy,
        HasFilters,
        HasSortBy,
        SoftDeletes;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        self::BRANCH_COLUMN_NAME,
        'reference_number',
        'count_date',
        'status',
        'notes',
        'approved_by',
        'approved_at',
        'cancelled_by',
        'cancelled_at',
        'cancellation_reason',
    ];

    /**
     * Get ordered export column definitions.
     *
     * @return array<int, array<string, mixed>>
     */
    public static function exportColumns(): array
    {
        return [
            ['path' => 'reference_number', 'label' => 'inventory::inventory_counts.table.reference_number'],
            ['path' => 'branch.name', 'label' => 'branch::branches.table.branch', 'with' => ['branch:id,name']],
            ['path' => 'count_date', 'label' => 'inventory::inventory_counts.table.count_date', 'type' => 'date'],
            ['path' => 'status', 'label' => 'inventory::inventory_counts.table.status'],
            ['path' => 'items_count', 'label' => 'inventory::inventory_counts.table.items_count'],
            ['path' => 'approvedBy.name', 'label' => 'inventory::inventory_counts.table.approved_by', 'with' => ['approvedBy:id,name']],
            ['path' => 'approved_at', 'label' => 'inventory::inventory_counts.table.approved_at', 'type' => 'datetime'],
            ['path' => 'createdBy.name', 'label' => 'admin::admin.table.created_by', 'with' => ['createdBy:id,name']],
            ['path' => 'created_at', 'label' => 'admin::admin.table.created_at', 'type' => 'datetime'],
            ['path' => 'updated_at', 'label' => 'admin::admin.table.updated_at', 'type' => 'datetime'],
        ];
    }

    /** {@inheritDoc} */
    public function allowedFilterKeys(): array
    {
        return ['search', 'from', 'to', 'status', self::BRANCH_COLUMN_NAME];
    }

    /**
     * Scope a query by inventory count reference.
     */
    public function scopeSearch(Builder $query, string $value): void
    {
        $query->where('reference_number', 'like', "%{$value}%");
    }

    /**
     * Get count items.
     */
    public function items(): HasMany
    {
        return $this->hasMany(InventoryCountItem::class);
    }

    /**
     * Get the approving user.
     */
    public function approvedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    /**
     * Get the cancelling user.
     */
    public function cancelledBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'cancelled_by');
    }

    /** {@inheritDoc} */
    protected function getSortableAttributes(): array
    {
        return [
            'reference_number',
            'count_date',
            'status',
            'branch_id',
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
            'count_date' => 'date',
            'status' => InventoryCountStatus::class,
            'approved_at' => 'datetime',
            'cancelled_at' => 'datetime',
        ];
    }
}
