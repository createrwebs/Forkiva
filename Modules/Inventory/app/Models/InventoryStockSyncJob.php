<?php

namespace Modules\Inventory\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Branch\Traits\HasBranch;
use Modules\Inventory\Enums\InventoryStockSyncOperation;
use Modules\Inventory\Enums\InventoryStockSyncStatus;
use Modules\Order\Models\Order;
use Modules\Support\Eloquent\Model;

/**
 * @property int $id
 * @property int $order_id
 * @property InventoryStockSyncOperation $operation
 * @property InventoryStockSyncStatus $status
 * @property string $idempotency_key
 * @property int $attempts
 * @property array<int, int>|null $movement_ids
 * @property array<string, mixed>|null $payload
 * @property string|null $last_error
 * @property Carbon|null $dispatched_at
 * @property string|null $dispatch_token
 * @property Carbon|null $processing_started_at
 * @property Carbon|null $last_attempt_at
 * @property Carbon|null $completed_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Order $order
 */
class InventoryStockSyncJob extends Model
{
    use HasBranch;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'branch_id',
        'order_id',
        'operation',
        'status',
        'idempotency_key',
        'attempts',
        'movement_ids',
        'payload',
        'last_error',
        'dispatched_at',
        'dispatch_token',
        'processing_started_at',
        'last_attempt_at',
        'completed_at',
    ];

    /**
     * Get the order whose stock is synchronized.
     */
    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class)
            ->withOutGlobalBranchPermission();
    }

    /**
     * Get model casts.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'operation' => InventoryStockSyncOperation::class,
            'status' => InventoryStockSyncStatus::class,
            'attempts' => 'integer',
            'movement_ids' => 'array',
            'payload' => 'array',
            'dispatched_at' => 'datetime',
            'processing_started_at' => 'datetime',
            'last_attempt_at' => 'datetime',
            'completed_at' => 'datetime',
        ];
    }
}
