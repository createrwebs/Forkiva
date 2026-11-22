<?php

namespace Modules\Inventory\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Support\Eloquent\Model;
use Modules\User\Models\User;

/**
 * @property int $id
 * @property int $stock_transfer_receipt_id
 * @property int $stock_transfer_item_id
 * @property string $quantity
 * @property string $unit_cost
 * @property string $total_cost
 * @property int|null $inbound_movement_id
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read StockTransferReceipt $receipt
 * @property-read StockTransferItem $transferItem
 * @property-read StockMovement|null $inboundMovement
 *
 * @mixin Builder
 */
class StockTransferReceiptItem extends Model
{
    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'stock_transfer_receipt_id',
        'stock_transfer_item_id',
        'quantity',
        'unit_cost',
        'total_cost',
        'inbound_movement_id',
    ];

    /**
     * Apply transfer branch visibility.
     */
    protected static function booted(): void
    {
        static::addGlobalScope('stock_transfer_branch_permission', function (Builder $query): void {
            $user = auth()->user();

            if ($user instanceof User && $user->assignedToBranch()) {
                $query->whereHas('receipt.transfer', function (Builder $transferQuery) use ($user): void {
                    $transferQuery
                        ->withTrashed()
                        ->withoutGlobalScope('stock_transfer_branch_permission')
                        ->where(function (Builder $branchQuery) use ($user): void {
                            $branchQuery
                                ->where('stock_transfers.source_branch_id', $user->branch_id)
                                ->orWhere('stock_transfers.destination_branch_id', $user->branch_id);
                        });
                });
            }
        });
    }

    /**
     * Get the owning receipt, including soft-deleted audit history.
     *
     * @return BelongsTo<StockTransferReceipt, $this>
     */
    public function receipt(): BelongsTo
    {
        return $this->belongsTo(StockTransferReceipt::class, 'stock_transfer_receipt_id')
            ->withTrashed();
    }

    /**
     * Get the transfer line allocated by this receipt.
     *
     * @return BelongsTo<StockTransferItem, $this>
     */
    public function transferItem(): BelongsTo
    {
        return $this->belongsTo(StockTransferItem::class, 'stock_transfer_item_id');
    }

    /**
     * Get the inbound movement, including soft-deleted audit history.
     *
     * @return BelongsTo<StockMovement, $this>
     */
    public function inboundMovement(): BelongsTo
    {
        return $this->belongsTo(StockMovement::class, 'inbound_movement_id')
            ->withOutGlobalBranchPermission()
            ->withTrashed();
    }

    /** {@inheritDoc} */
    protected function casts(): array
    {
        return [
            'quantity' => 'decimal:6',
            'unit_cost' => 'decimal:6',
            'total_cost' => 'decimal:6',
        ];
    }
}
