<?php

namespace Modules\DailyFinance\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;
use Modules\ActivityLog\Traits\HasActivityLog;
use Modules\Branch\Models\Branch;
use Modules\Branch\Traits\HasBranch;
use Modules\Support\Eloquent\Model;
use Modules\Support\Traits\HasCreatedBy;
use Modules\User\Models\User;
use Spatie\Activitylog\Models\Activity;

/**
 * @property int $id
 * @property int $payment_settlement_id
 * @property int $branch_id
 * @property int $created_by
 * @property string $account_fingerprint
 * @property string $amount Decimal bank-credit amount retained at storage precision.
 * @property Carbon $received_date
 * @property string $reference
 * @property bool $is_final
 * @property string|null $variance_reason
 * @property string $request_key
 * @property string $request_hash
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * @property-read User $createdBy
 * @property-read Branch $branch
 * @property-read PaymentSettlement $settlement
 * @property-read Collection<int, Activity> $activities
 *
 * @mixin Builder
 */
class PaymentSettlementReceipt extends Model
{
    use HasActivityLog, HasBranch, HasCreatedBy;

    /**
     * The table retaining confirmed bank-credit evidence.
     *
     * @var string
     */
    protected $table = 'daily_finance_payment_settlement_receipts';

    /**
     * Bank-credit evidence and replay metadata accepted during receipt creation.
     *
     * @var list<string>
     */
    protected $fillable = [
        'branch_id',
        'account_fingerprint',
        'amount',
        'received_date',
        'reference',
        'is_final',
        'variance_reason',
        'request_key',
        'request_hash',
    ];

    /**
     * Bank-credit evidence is append-only after confirmation.
     */
    protected static function booted(): void
    {
        static::updating(fn () => throw new LogicException('Settlement receipts are immutable.'));
        static::deleting(fn () => throw new LogicException('Settlement receipts are immutable.'));
    }

    /**
     * Resolve the batch whose bank-credit evidence this receipt preserves.
     * Keep the historical relation independent of the actor's current branch scope.
     *
     * @return BelongsTo<PaymentSettlement, $this>
     */
    public function settlement(): BelongsTo
    {
        return $this->belongsTo(PaymentSettlement::class, 'payment_settlement_id')
            ->withOutGlobalBranchPermission();
    }

    /**
     * Retain decimal amounts as strings and bank-credit dates as calendar dates.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'amount' => 'decimal:4',
            'received_date' => 'date',
            'is_final' => 'boolean',
        ];
    }
}
