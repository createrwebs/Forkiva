<?php

namespace Modules\DailyFinance\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Branch\Models\Branch;
use Modules\Branch\Traits\HasBranch;
use Modules\DailyFinance\Enums\CashHandoverStatus;
use Modules\DailyFinance\Traits\HasFinanceReference;
use Modules\Pos\Models\PosSession;
use Modules\Support\Eloquent\Model;
use Modules\Support\Traits\HasCreatedBy;
use Modules\User\Models\User;

/**
 * @property int $id
 * @property string $reference_no
 * @property int $pos_session_id
 * @property int $branch_id
 * @property int $created_by
 * @property int $cashier_id
 * @property int $receiver_id
 * @property int|null $cancelled_by
 * @property string $request_key
 * @property string $currency
 * @property string $counted_cash
 * @property string $amount
 * @property string|null $received_amount
 * @property CashHandoverStatus $status
 * @property string|null $notes
 * @property string|null $variance_reason
 * @property string|null $cancellation_reason
 * @property Carbon|null $received_at
 * @property Carbon|null $cancelled_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read PosSession $session
 * @property-read Branch $branch
 * @property-read User $createdBy
 * @property-read User $cashier
 * @property-read User $receiver
 * @property-read User|null $cancelledBy
 *
 * @mixin Builder
 */
class CashHandover extends Model
{
    use HasBranch, HasCreatedBy, HasFinanceReference;

    protected $table = 'daily_finance_cash_handovers';

    protected $fillable = [
        'pos_session_id',
        self::BRANCH_COLUMN_NAME,
        'cashier_id',
        'receiver_id',
        'cancelled_by',
        'request_key',
        'currency',
        'counted_cash',
        'amount',
        'received_amount',
        'status',
        'notes',
        'variance_reason',
        'cancellation_reason',
        'received_at',
        'cancelled_at',
    ];

    /**
     * Resolve the source session even after its soft deletion.
     */
    public function session(): BelongsTo
    {
        return $this->belongsTo(PosSession::class, 'pos_session_id')
            ->withOutGlobalBranchPermission()
            ->withTrashed();
    }

    /**
     * Resolve the responsible cashier independently of current activity or branch assignment.
     */
    public function cashier(): BelongsTo
    {
        return $this->belongsTo(User::class, 'cashier_id')
            ->withOutGlobalBranchPermission()
            ->withoutGlobalActive()
            ->withTrashed();
    }

    /**
     * Resolve the designated receiver while retaining access to historical identities.
     */
    public function receiver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'receiver_id')
            ->withOutGlobalBranchPermission()
            ->withoutGlobalActive()
            ->withTrashed();
    }

    /**
     * Resolve the actor who cancelled a pending handover, when present.
     */
    public function cancelledBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'cancelled_by')
            ->withOutGlobalBranchPermission()
            ->withoutGlobalActive()
            ->withTrashed();
    }

    protected function referencePrefix(): string
    {
        return 'CH';
    }

    /**
     * Preserve decimal strings for financial decisions and cast audit dates and status.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => CashHandoverStatus::class,
            'amount' => 'decimal:4',
            'received_amount' => 'decimal:4',
            'counted_cash' => 'decimal:4',
            'received_at' => 'datetime',
            'cancelled_at' => 'datetime',
        ];
    }
}
