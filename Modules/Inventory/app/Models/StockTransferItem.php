<?php

namespace Modules\Inventory\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Modules\Support\Eloquent\Model;
use Modules\User\Models\User;

/**
 * @property int $id
 * @property int $stock_transfer_id
 * @property int $source_ingredient_id
 * @property int $destination_ingredient_id
 * @property int $unit_id
 * @property string $requested_quantity
 * @property string $dispatched_quantity
 * @property string $received_quantity
 * @property string $discrepancy_quantity
 * @property string|null $unit_cost
 * @property string|null $total_cost
 * @property string|null $discrepancy_reason
 * @property int|null $outbound_movement_id
 * @property int|null $outbound_reversal_movement_id
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read StockTransfer $transfer
 * @property-read Ingredient $sourceIngredient
 * @property-read Ingredient $destinationIngredient
 * @property-read Unit $unit
 * @property-read StockMovement|null $outboundMovement
 * @property-read StockMovement|null $outboundReversalMovement
 * @property-read Collection<int, StockTransferReceiptItem> $receiptItems
 *
 * @mixin Builder
 */
class StockTransferItem extends Model
{
    /**
     * Apply transfer branch visibility.
     */
    protected static function booted(): void
    {
        static::addGlobalScope('stock_transfer_branch_permission', function (Builder $query): void {
            $user = auth()->user();

            if ($user instanceof User && $user->assignedToBranch()) {
                $query->whereHas('transfer', function (Builder $transferQuery) use ($user): void {
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
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'stock_transfer_id',
        'source_ingredient_id',
        'destination_ingredient_id',
        'unit_id',
        'requested_quantity',
        'dispatched_quantity',
        'received_quantity',
        'discrepancy_quantity',
        'unit_cost',
        'total_cost',
        'discrepancy_reason',
        'outbound_movement_id',
        'outbound_reversal_movement_id',
    ];

    /**
     * Get the owning transfer, including a soft-deleted draft.
     *
     * @return BelongsTo<StockTransfer, $this>
     */
    public function transfer(): BelongsTo
    {
        return $this->belongsTo(StockTransfer::class, 'stock_transfer_id')->withTrashed();
    }

    /**
     * Get the source ingredient without current-user branch filtering.
     *
     * @return BelongsTo<Ingredient, $this>
     */
    public function sourceIngredient(): BelongsTo
    {
        return $this->belongsTo(Ingredient::class, 'source_ingredient_id')
            ->withOutGlobalBranchPermission()
            ->withTrashed();
    }

    /**
     * Get the destination ingredient without current-user branch filtering.
     *
     * @return BelongsTo<Ingredient, $this>
     */
    public function destinationIngredient(): BelongsTo
    {
        return $this->belongsTo(Ingredient::class, 'destination_ingredient_id')
            ->withOutGlobalBranchPermission()
            ->withTrashed();
    }

    /**
     * Get the frozen stock unit.
     *
     * @return BelongsTo<Unit, $this>
     */
    public function unit(): BelongsTo
    {
        return $this->belongsTo(Unit::class)->withTrashed();
    }

    /**
     * Get the outbound movement, including soft-deleted audit history.
     *
     * @return BelongsTo<StockMovement, $this>
     */
    public function outboundMovement(): BelongsTo
    {
        return $this->belongsTo(StockMovement::class, 'outbound_movement_id')
            ->withOutGlobalBranchPermission()
            ->withTrashed();
    }

    /**
     * Get the outbound reversal movement, including soft-deleted audit history.
     *
     * @return BelongsTo<StockMovement, $this>
     */
    public function outboundReversalMovement(): BelongsTo
    {
        return $this->belongsTo(StockMovement::class, 'outbound_reversal_movement_id')
            ->withOutGlobalBranchPermission()
            ->withTrashed();
    }

    /**
     * Get all receipt allocations for this transfer line.
     *
     * @return HasMany<StockTransferReceiptItem, $this>
     */
    public function receiptItems(): HasMany
    {
        return $this->hasMany(StockTransferReceiptItem::class);
    }

    /** {@inheritDoc} */
    protected function casts(): array
    {
        return [
            'requested_quantity' => 'decimal:6',
            'dispatched_quantity' => 'decimal:6',
            'received_quantity' => 'decimal:6',
            'discrepancy_quantity' => 'decimal:6',
            'unit_cost' => 'decimal:6',
            'total_cost' => 'decimal:6',
        ];
    }
}
