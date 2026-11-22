<?php

namespace Modules\DailyFinance\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use LogicException;
use Modules\ActivityLog\Traits\HasActivityLog;
use Modules\Branch\Models\Branch;
use Modules\Branch\Traits\HasBranch;
use Modules\DailyFinance\Enums\PaymentSettlementStatus;
use Modules\DailyFinance\Traits\HasFinanceReference;
use Modules\Payment\Enums\PaymentMethod;
use Modules\Support\Eloquent\Model;
use Modules\Support\Traits\HasCreatedBy;
use Modules\User\Models\User;

/**
 * @property int $id
 * @property string $reference_no
 * @property int $branch_id
 * @property int $created_by
 * @property PaymentMethod $method
 * @property PaymentSettlementStatus $status
 * @property string $currency
 * @property string $timezone
 * @property string $reference
 * @property string|null $account_key
 * @property string $destination_name
 * @property string $account_fingerprint
 * @property string $gross_amount
 * @property string $fees
 * @property string $expected_amount
 * @property string $request_key
 * @property string $request_hash
 * @property string|null $notes
 * @property int|null $cancelled_by
 * @property Carbon|null $cancelled_at
 * @property string|null $cancellation_reason
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * @property int $items_count
 * @property-read Collection<int, PaymentSettlementItem> $items
 * @property-read Collection<int, PaymentSettlementReceipt> $receipts
 * @property-read Branch $branch
 * @property-read User $createdBy
 * @property-read User|null $cancelledBy
 */
class PaymentSettlement extends Model
{
    use HasActivityLog, HasBranch, HasCreatedBy, HasFinanceReference;

    protected $table = 'daily_finance_payment_settlements';

    protected $fillable = [
        'branch_id',
        'method',
        'currency',
        'timezone',
        'status',
        'reference',
        'account_key',
        'destination_name',
        'account_fingerprint',
        'gross_amount',
        'fees',
        'expected_amount',
        'request_key',
        'request_hash',
        'notes',
        'cancelled_by',
        'cancelled_at',
        'cancellation_reason',
    ];

    protected function referencePrefix(): string
    {
        return 'PS';
    }

    protected function casts(): array
    {
        return [
            'method' => PaymentMethod::class,
            'status' => PaymentSettlementStatus::class,
            'gross_amount' => 'decimal:4',
            'fees' => 'decimal:4',
            'expected_amount' => 'decimal:4',
            'cancelled_at' => 'datetime',
        ];
    }

    /**
     * Retain the selected collection snapshots for historical review.
     */
    public function items(): HasMany
    {
        return $this->hasMany(PaymentSettlementItem::class);
    }

    /**
     * Read the immutable bank credits recorded for this batch.
     */
    public function receipts(): HasMany
    {
        return $this->hasMany(PaymentSettlementReceipt::class);
    }

    /**
     * Resolve the cancellation actor even after their activity or branch assignment changes.
     */
    public function cancelledBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'cancelled_by')
            ->withOutGlobalBranchPermission()
            ->withoutGlobalActive()
            ->withTrashed();
    }

    /**
     * Approved source amounts and destinations cannot be rewritten by later deposit activity.
     */
    protected static function booted(): void
    {
        static::updating(function (self $record): void {
            $allowed = ['status', 'cancelled_by', 'cancelled_at', 'cancellation_reason', 'updated_at'];
            if (array_diff(array_keys($record->getDirty()), $allowed) !== []) {
                throw new LogicException('Settlement source snapshots are immutable.');
            }
        });
        static::deleting(fn () => throw new LogicException('Settlement history cannot be deleted.'));
    }
}
