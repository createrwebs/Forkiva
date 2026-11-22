<?php

namespace Modules\Inventory\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Branch\Traits\HasBranch;
use Modules\Support\Eloquent\Model;
use Modules\Support\Money;

/**
 * Stores the authoritative moving-average inventory balance for one ingredient.
 *
 * @property int $id
 * @property int $ingredient_id
 * @property string $quantity_on_hand
 * @property Money $inventory_value
 * @property Money|null $average_unit_cost
 * @property string $currency
 * @property bool $requires_remediation
 * @property string|null $remediation_reason
 * @property Carbon|null $reconciliation_baseline_at
 * @property int|null $reconciliation_baseline_movement_id
 * @property string|null $baseline_quantity
 * @property string|null $baseline_inventory_value
 * @property string|null $baseline_average_unit_cost
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Ingredient $ingredient
 */
class InventoryCostBalance extends Model
{
    use HasBranch;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'branch_id',
        'ingredient_id',
        'quantity_on_hand',
        'inventory_value',
        'average_unit_cost',
        'currency',
        'requires_remediation',
        'remediation_reason',
        'reconciliation_baseline_at',
        'reconciliation_baseline_movement_id',
        'baseline_quantity',
        'baseline_inventory_value',
        'baseline_average_unit_cost',
    ];

    /**
     * Get the ingredient owned by this balance.
     */
    public function ingredient(): BelongsTo
    {
        return $this->belongsTo(Ingredient::class)
            ->withOutGlobalBranchPermission()
            ->withTrashed();
    }

    /**
     * Get inventory value as money.
     */
    protected function inventoryValue(): Attribute
    {
        return Attribute::get(
            fn ($value): Money => new Money((float) $value, $this->currency),
        );
    }

    /**
     * Get average unit cost as money.
     */
    protected function averageUnitCost(): Attribute
    {
        return Attribute::get(
            fn ($value): ?Money => is_null($value)
                ? null
                : new Money((float) $value, $this->currency),
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
            'quantity_on_hand' => 'decimal:6',
            'baseline_quantity' => 'decimal:6',
            'requires_remediation' => 'boolean',
            'reconciliation_baseline_at' => 'datetime',
        ];
    }
}
