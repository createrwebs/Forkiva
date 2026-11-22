<?php

namespace Modules\Inventory\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Branch\Traits\HasBranch;
use Modules\Support\Eloquent\Model;

/**
 * @property int $id
 * @property int $inventory_count_id
 * @property int $ingredient_id
 * @property int $unit_id
 * @property float $expected_quantity
 * @property float $counted_quantity
 * @property float $variance_quantity
 * @property int|null $snapshot_movement_id
 * @property int|null $generated_movement_id
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read InventoryCount $inventoryCount
 * @property-read Ingredient $ingredient
 * @property-read Unit $unit
 * @property-read StockMovement|null $snapshotMovement
 * @property-read StockMovement|null $generatedMovement
 */
class InventoryCountItem extends Model
{
    use HasBranch;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        self::BRANCH_COLUMN_NAME,
        'inventory_count_id',
        'ingredient_id',
        'unit_id',
        'expected_quantity',
        'counted_quantity',
        'variance_quantity',
        'snapshot_movement_id',
        'generated_movement_id',
    ];

    /**
     * Get the inventory count.
     */
    public function inventoryCount(): BelongsTo
    {
        return $this->belongsTo(InventoryCount::class);
    }

    /**
     * Get the counted ingredient.
     */
    public function ingredient(): BelongsTo
    {
        return $this->belongsTo(Ingredient::class)
            ->withOutGlobalBranchPermission()
            ->withTrashed();
    }

    /**
     * Get the quantity unit.
     */
    public function unit(): BelongsTo
    {
        return $this->belongsTo(Unit::class)->withTrashed();
    }

    /**
     * Get the latest movement captured by the draft snapshot.
     */
    public function snapshotMovement(): BelongsTo
    {
        return $this->belongsTo(StockMovement::class, 'snapshot_movement_id')
            ->withOutGlobalBranchPermission()
            ->withTrashed();
    }

    /**
     * Get the adjustment movement generated during approval.
     */
    public function generatedMovement(): BelongsTo
    {
        return $this->belongsTo(StockMovement::class, 'generated_movement_id')
            ->withOutGlobalBranchPermission()
            ->withTrashed();
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'expected_quantity' => 'float',
            'counted_quantity' => 'float',
            'variance_quantity' => 'float',
        ];
    }
}
