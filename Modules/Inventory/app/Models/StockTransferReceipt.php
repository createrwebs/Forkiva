<?php

namespace Modules\Inventory\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Modules\ActivityLog\Traits\HasActivityLog;
use Modules\Support\Eloquent\Model;
use Modules\User\Models\User;

/**
 * @property int $id
 * @property string $uuid
 * @property int $stock_transfer_id
 * @property-read StockTransfer $transfer
 * @property string|null $reference_number
 * @property string $idempotency_key
 * @property string|null $notes
 * @property int|null $received_by
 * @property-read User|null $receivedBy
 * @property Carbon $received_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property Carbon|null $deleted_at
 * @property-read Collection<int, StockTransferReceiptItem> $items
 *
 * @mixin Builder
 */
class StockTransferReceipt extends Model
{
    use HasActivityLog, SoftDeletes;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'uuid',
        'stock_transfer_id',
        'reference_number',
        'idempotency_key',
        'notes',
        'received_by',
        'received_at',
    ];

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
     * Get the owning transfer, including soft-deleted history.
     *
     * @return BelongsTo<StockTransfer, $this>
     */
    public function transfer(): BelongsTo
    {
        return $this->belongsTo(StockTransfer::class, 'stock_transfer_id')->withTrashed();
    }

    /**
     * Get the immutable receipt lines.
     *
     * @return HasMany<StockTransferReceiptItem, $this>
     */
    public function items(): HasMany
    {
        return $this->hasMany(StockTransferReceiptItem::class);
    }

    /**
     * Get the user who received the stock.
     *
     * @return BelongsTo<User, $this>
     */
    public function receivedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'received_by')
            ->withOutGlobalBranchPermission()
            ->withoutGlobalActive()
            ->withTrashed();
    }

    /** {@inheritDoc} */
    protected function casts(): array
    {
        return ['received_at' => 'datetime'];
    }
}
