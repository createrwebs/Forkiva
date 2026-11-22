<?php

namespace Modules\Inventory\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;
use LogicException;
use Modules\ActivityLog\Traits\HasActivityLog;
use Modules\Branch\Models\Branch;
use Modules\Export\Contracts\ModelExportDefinitionInterface;
use Modules\Inventory\Enums\StockTransferStatus;
use Modules\Support\Eloquent\Model;
use Modules\Support\Traits\HasCreatedBy;
use Modules\Support\Traits\HasFilters;
use Modules\Support\Traits\HasSortBy;
use Modules\User\Models\User;

/**
 * @property int $id
 * @property string $uuid
 * @property string|null $reference_number
 * @property int $source_branch_id
 * @property int $destination_branch_id
 * @property StockTransferStatus $status
 * @property string|null $currency
 * @property string|null $currency_rate
 * @property Carbon|null $expected_at
 * @property string|null $notes
 * @property int|null $approved_by
 * @property Carbon|null $approved_at
 * @property int|null $dispatched_by
 * @property Carbon|null $dispatched_at
 * @property int|null $completed_by
 * @property Carbon|null $completed_at
 * @property int|null $cancelled_by
 * @property Carbon|null $cancelled_at
 * @property string|null $cancellation_reason
 * @property int|null $voided_by
 * @property Carbon|null $voided_at
 * @property string|null $void_reason
 * @property int|null $created_by
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property Carbon|null $deleted_at
 * @property-read Branch $sourceBranch
 * @property-read Branch $destinationBranch
 * @property-read Collection<int, StockTransferItem> $items
 * @property-read Collection<int, StockTransferReceipt> $receipts
 * @property-read User|null $createdBy
 * @property-read User|null $approvedBy
 * @property-read User|null $dispatchedBy
 * @property-read User|null $completedBy
 * @property-read User|null $cancelledBy
 * @property-read User|null $voidedBy
 *
 * @mixin Builder
 */
class StockTransfer extends Model implements ModelExportDefinitionInterface
{
    use HasActivityLog,
        HasCreatedBy,
        HasFilters,
        HasSortBy,
        SoftDeletes;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'uuid',
        'reference_number',
        'source_branch_id',
        'destination_branch_id',
        'status',
        'currency',
        'currency_rate',
        'currency_rate',
        'expected_at',
        'notes',
        'approved_by',
        'approved_at',
        'dispatched_by',
        'dispatched_at',
        'completed_by',
        'completed_at',
        'cancelled_by',
        'cancelled_at',
        'cancellation_reason',
        'voided_by',
        'voided_at',
        'void_reason',
        'created_by',
    ];

    /**
     * Get ordered export column definitions.
     *
     * @return array<int, array<string, mixed>>
     */
    public static function exportColumns(): array
    {
        return [
            ['path' => 'reference_number', 'label' => 'inventory::stock_transfers.table.reference_number'],
            ['path' => 'sourceBranch.name', 'label' => 'inventory::stock_transfers.table.source_branch', 'with' => ['sourceBranch:id,name']],
            ['path' => 'destinationBranch.name', 'label' => 'inventory::stock_transfers.table.destination_branch', 'with' => ['destinationBranch:id,name']],
            ['path' => 'status', 'label' => 'inventory::stock_transfers.table.status'],
            ['path' => 'currency_rate', 'label' => 'inventory::stock_transfers.table.currency_rate'],
            ['path' => 'expected_at', 'label' => 'inventory::stock_transfers.table.expected_at', 'type' => 'datetime'],
            ['path' => 'dispatched_at', 'label' => 'inventory::stock_transfers.table.dispatched_at', 'type' => 'datetime'],
            ['path' => 'completed_at', 'label' => 'inventory::stock_transfers.table.completed_at', 'type' => 'datetime'],
            ['path' => 'createdBy.name', 'label' => 'admin::admin.table.created_by', 'with' => ['createdBy:id,name']],
            ['path' => 'created_at', 'label' => 'admin::admin.table.created_at', 'type' => 'datetime'],
        ];
    }

    /**
     * Apply two-branch visibility and protect immutable transfer history.
     */
    protected static function booted(): void
    {
        static::creating(function (StockTransfer $transfer): void {
            $transfer->uuid ??= (string) Str::uuid();
        });

        static::addGlobalScope('stock_transfer_branch_permission', function (Builder $query): void {
            $user = auth()->user();

            if ($user instanceof User && $user->assignedToBranch()) {
                $query->where(function (Builder $query) use ($user): void {
                    $query
                        ->where('stock_transfers.source_branch_id', $user->branch_id)
                        ->orWhere('stock_transfers.destination_branch_id', $user->branch_id);
                });
            }
        });

        static::updating(function (StockTransfer $transfer): void {
            $original = StockTransferStatus::tryFrom((string) $transfer->getRawOriginal('status'));

            if ($transfer->isDirty('status')) {
                $next = $transfer->status;

                if (! $original || ! $next instanceof StockTransferStatus || ! $original->canTransitionTo($next)) {
                    throw new LogicException(__('inventory::messages.stock_transfer_status_invalid'));
                }
            }

            if (
                $original !== StockTransferStatus::Draft
                && $transfer->isDirty([
                    'reference_number',
                    'source_branch_id',
                    'destination_branch_id',
                    'expected_at',
                    'notes',
                ])
            ) {
                throw new LogicException(__('inventory::messages.stock_transfer_document_frozen'));
            }

            if ($transfer->isDirty('uuid')) {
                throw new LogicException(__('inventory::messages.stock_transfer_document_frozen'));
            }

            if (
                $transfer->isDirty('reference_number')
                && filled($transfer->getRawOriginal('reference_number'))
            ) {
                throw new LogicException(__('inventory::messages.stock_transfer_document_frozen'));
            }

            foreach ([
                'currency',
                'currency_rate',
                'approved_by',
                'approved_at',
                'dispatched_by',
                'dispatched_at',
                'completed_by',
                'completed_at',
                'cancelled_by',
                'cancelled_at',
                'cancellation_reason',
                'voided_by',
                'voided_at',
                'void_reason',
            ] as $auditField) {
                if ($transfer->isDirty($auditField) && filled($transfer->getRawOriginal($auditField))) {
                    throw new LogicException(__('inventory::messages.stock_transfer_document_frozen'));
                }
            }
        });

        static::deleting(function (StockTransfer $transfer): void {
            if ($transfer->isForceDeleting() || $transfer->status !== StockTransferStatus::Draft) {
                throw new LogicException(__('inventory::messages.stock_transfer_delete_forbidden'));
            }
        });
    }

    /** {@inheritDoc} */
    public function allowedFilterKeys(): array
    {
        return ['search', 'status', 'source_branch_id', 'destination_branch_id', 'from', 'to'];
    }

    /**
     * Scope a query by transfer reference.
     *
     * @param  Builder<StockTransfer>  $query
     */
    public function scopeSearch(Builder $query, string $value): void
    {
        $query->where('reference_number', 'like', "%{$value}%");
    }

    /**
     * Get the source branch, including inactive or soft-deleted history.
     *
     * @return BelongsTo<Branch, $this>
     */
    public function sourceBranch(): BelongsTo
    {
        return $this->belongsTo(Branch::class, 'source_branch_id')
            ->withoutGlobalActive()
            ->withTrashed();
    }

    /**
     * Get the destination branch, including inactive or soft-deleted history.
     *
     * @return BelongsTo<Branch, $this>
     */
    public function destinationBranch(): BelongsTo
    {
        return $this->belongsTo(Branch::class, 'destination_branch_id')
            ->withoutGlobalActive()
            ->withTrashed();
    }

    /**
     * Get the transfer lines.
     *
     * @return HasMany<StockTransferItem, $this>
     */
    public function items(): HasMany
    {
        return $this->hasMany(StockTransferItem::class);
    }

    /**
     * Get all partial-receipt documents, including soft-deleted audit history.
     *
     * @return HasMany<StockTransferReceipt, $this>
     */
    public function receipts(): HasMany
    {
        return $this->hasMany(StockTransferReceipt::class)->withTrashed();
    }

    /**
     * Get the user who approved the transfer.
     *
     * @return BelongsTo<User, $this>
     */
    public function approvedBy(): BelongsTo
    {
        return $this->historicalUser('approved_by');
    }

    /**
     * Build an audit-user relation without current branch, active, or delete scopes.
     *
     * @return BelongsTo<User, $this>
     */
    private function historicalUser(string $foreignKey): BelongsTo
    {
        return $this->belongsTo(User::class, $foreignKey)
            ->withOutGlobalBranchPermission()
            ->withoutGlobalActive()
            ->withTrashed();
    }

    /**
     * Get the user who dispatched the transfer.
     *
     * @return BelongsTo<User, $this>
     */
    public function dispatchedBy(): BelongsTo
    {
        return $this->historicalUser('dispatched_by');
    }

    /**
     * Get the user who completed or discrepancy-closed the transfer.
     *
     * @return BelongsTo<User, $this>
     */
    public function completedBy(): BelongsTo
    {
        return $this->historicalUser('completed_by');
    }

    /**
     * Get the user who cancelled the transfer.
     *
     * @return BelongsTo<User, $this>
     */
    public function cancelledBy(): BelongsTo
    {
        return $this->historicalUser('cancelled_by');
    }

    /**
     * Get the user who voided the dispatched transfer.
     *
     * @return BelongsTo<User, $this>
     */
    public function voidedBy(): BelongsTo
    {
        return $this->historicalUser('voided_by');
    }

    /** {@inheritDoc} */
    protected function getSortableAttributes(): array
    {
        return [
            'reference_number',
            'source_branch_id',
            'destination_branch_id',
            'status',
            'expected_at',
            'dispatched_at',
            'completed_at',
        ];
    }

    /** {@inheritDoc} */
    protected function casts(): array
    {
        return [
            'status' => StockTransferStatus::class,
            'currency_rate' => 'decimal:8',
            'expected_at' => 'datetime',
            'approved_at' => 'datetime',
            'dispatched_at' => 'datetime',
            'completed_at' => 'datetime',
            'cancelled_at' => 'datetime',
            'voided_at' => 'datetime',
        ];
    }
}
