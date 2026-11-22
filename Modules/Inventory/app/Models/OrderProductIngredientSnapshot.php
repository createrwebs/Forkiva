<?php

namespace Modules\Inventory\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Branch\Traits\HasBranch;
use Modules\Order\Models\Order;
use Modules\Order\Models\OrderProduct;
use Modules\Support\Eloquent\Model;

/**
 * @property int $id
 * @property int $order_id
 * @property int $order_product_id
 * @property int $ingredient_id
 * @property int $unit_id
 * @property int $branch_id
 * @property float $net_quantity_per_product
 * @property float $gross_quantity_per_product
 * @property float $order_product_quantity
 * @property float $quantity
 * @property float $loss_pct
 * @property bool $is_returnable
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Order $order
 * @property-read OrderProduct $orderProduct
 * @property-read Ingredient $ingredient
 * @property-read Unit $unit
 */
class OrderProductIngredientSnapshot extends Model
{
    use HasBranch;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'order_id',
        'order_product_id',
        'ingredient_id',
        'unit_id',
        'branch_id',
        'net_quantity_per_product',
        'gross_quantity_per_product',
        'order_product_quantity',
        'quantity',
        'loss_pct',
        'is_returnable',
    ];

    /**
     * Get order
     */
    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class)->withOutGlobalBranchPermission();
    }

    /**
     * Get order product
     */
    public function orderProduct(): BelongsTo
    {
        return $this->belongsTo(OrderProduct::class);
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
     * Get a unit model
     */
    public function unit(): BelongsTo
    {
        return $this->belongsTo(Unit::class)->withTrashed();
    }

    /**
     * Get model casts.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'net_quantity_per_product' => 'float',
            'gross_quantity_per_product' => 'float',
            'order_product_quantity' => 'float',
            'quantity' => 'float',
            'loss_pct' => 'float',
            'is_returnable' => 'boolean',
        ];
    }
}
