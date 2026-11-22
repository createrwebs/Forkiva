<?php

namespace Modules\Inventory\Models;

use Illuminate\Database\Eloquent\Model;
use Modules\Core\Traits\HasTenant;

class StockLot extends Model
{
    use HasTenant;

    protected $fillable = [
        'tenant_id',
        'branch_id',
        'product_id',
        'product_variant_id',
        'lot_number',
        'expires_at',
        'quantity_remaining',
        'unit_cost',
    ];

    protected function casts(): array
    {
        return [
            'expires_at' => 'datetime',
            'quantity_remaining' => 'decimal:4',
            'unit_cost' => 'decimal:4',
        ];
    }
}
