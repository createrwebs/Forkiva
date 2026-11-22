<?php

namespace Modules\Approval\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;
use Modules\ActivityLog\Traits\HasActivityLog;
use Modules\Approval\Enums\ApprovalStepStatus;
use Modules\Support\Eloquent\Model;
use Modules\Support\Traits\HasCreatedBy;
use Modules\User\Models\User;

/**
 * @property int $id
 * @property int $approval_request_id
 * @property int $level
 * @property string $name
 * @property string|null $minimum_amount
 * @property string|null $maximum_amount
 * @property string|null $permission
 * @property ApprovalStepStatus $status
 * @property int|null $decided_by
 * @property Carbon|null $decided_at
 * @property string|null $reason
 * @property int|null $created_by
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * @property Carbon|null $deleted_at
 * @property-read ApprovalRequest $request
 * @property-read User|null $reviewer
 * @property-read User|null $createdBy
 */
class ApprovalStep extends Model
{
    use HasActivityLog, HasCreatedBy, SoftDeletes;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'approval_request_id',
        'level',
        'name',
        'minimum_amount',
        'maximum_amount',
        'permission',
        'status',
        'decided_by',
        'decided_at',
        'reason',
        'created_by',
    ];

    /** {@inheritDoc} */
    protected function casts(): array
    {
        return [
            'status' => ApprovalStepStatus::class,
            'minimum_amount' => 'decimal:8',
            'maximum_amount' => 'decimal:8',
            'decided_at' => 'datetime',
        ];
    }

    /**
     * Get the approval request that owns this decision level.
     */
    public function request(): BelongsTo
    {
        return $this->belongsTo(ApprovalRequest::class, 'approval_request_id');
    }

    /**
     * Get the user who approved or rejected this level.
     */
    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'decided_by')
            ->withOutGlobalBranchPermission()
            ->withoutGlobalActive()
            ->withTrashed();
    }
}
