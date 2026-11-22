<?php

namespace Modules\Pos\Models;

use Illuminate\Database\Eloquent\Model;
use Modules\Core\Traits\HasTenant;

class PosOfflineSyncReceipt extends Model
{
    use HasTenant;

    protected $fillable = [
        'tenant_id',
        'branch_id',
        'client_uuid',
        'offline_reference',
        'payment_method',
        'total',
        'needs_stock_reconciliation',
        'payload',
        'synced_at',
    ];

    protected function casts(): array
    {
        return [
            'total' => 'decimal:2',
            'needs_stock_reconciliation' => 'boolean',
            'payload' => 'array',
            'synced_at' => 'datetime',
        ];
    }
}
