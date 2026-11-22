<?php

namespace Modules\Pos\Models;

use Illuminate\Database\Eloquent\Model;
use Modules\Core\Traits\HasTenant;

class PosHeldCart extends Model
{
    use HasTenant;

    protected $fillable = [
        'tenant_id',
        'branch_id',
        'label',
        'items',
        'resumed_at',
    ];

    protected function casts(): array
    {
        return [
            'items' => 'array',
            'resumed_at' => 'datetime',
        ];
    }
}
