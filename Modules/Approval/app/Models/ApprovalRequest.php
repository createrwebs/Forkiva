<?php

namespace Modules\Approval\Models;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model as EloquentModel;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;
use Modules\ActivityLog\Traits\HasActivityLog;
use Modules\Approval\Enums\ApprovalRequestStatus;
use Modules\Branch\Models\Branch;
use Modules\Support\Eloquent\Model;
use Modules\Support\Traits\HasCreatedBy;
use Modules\User\Models\User;

/**
 * @property int $id
 * @property int|null $subject_id
 * @property string|null $subject_type
 * @property int|null $branch_id
 * @property int $maker_id
 * @property string $workflow
 * @property ApprovalRequestStatus $status
 * @property string|null $amount
 * @property string|null $currency
 * @property int $version
 * @property string $payload_fingerprint
 * @property array<string, mixed> $payload
 * @property array<string, mixed>|null $metadata
 * @property Carbon|null $approved_at
 * @property Carbon|null $rejected_at
 * @property Carbon|null $invalidated_at
 * @property int|null $invalidated_by
 * @property string|null $invalidation_reason
 * @property int|null $created_by
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * @property Carbon|null $deleted_at
 * @property-read EloquentModel|null $subject
 * @property-read Branch|null $branch
 * @property-read User $maker
 * @property-read User|null $invalidatedBy
 * @property-read User|null $createdBy
 * @property-read Collection<int, ApprovalStep> $steps
 */
class ApprovalRequest extends Model
{
    use HasActivityLog, HasCreatedBy, SoftDeletes;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'subject_id',
        'subject_type',
        'branch_id',
        'maker_id',
        'workflow',
        'status',
        'amount',
        'currency',
        'version',
        'payload_fingerprint',
        'payload',
        'metadata',
        'approved_at',
        'rejected_at',
        'invalidated_at',
        'invalidated_by',
        'invalidation_reason',
        'created_by',
    ];

    /**
     * Get the protected business record, when one was attached.
     */
    public function subject(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * Get the branch that owns this approval request.
     */
    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class)
            ->withoutGlobalActive()
            ->withTrashed();
    }

    /**
     * Get the user who submitted the protected operation.
     */
    public function maker(): BelongsTo
    {
        return $this->belongsTo(User::class, 'maker_id')
            ->withOutGlobalBranchPermission()
            ->withoutGlobalActive()
            ->withTrashed();
    }

    /**
     * Get the user who invalidated the request after a material change.
     */
    public function invalidatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'invalidated_by')
            ->withOutGlobalBranchPermission()
            ->withoutGlobalActive()
            ->withTrashed();
    }

    /**
     * Get the ordered decision levels belonging to this request.
     */
    public function steps(): HasMany
    {
        return $this->hasMany(ApprovalStep::class)->orderBy('level');
    }

    /** {@inheritDoc} */
    protected function casts(): array
    {
        return [
            'status' => ApprovalRequestStatus::class,
            'payload' => 'array',
            'metadata' => 'array',
            'amount' => 'decimal:8',
            'approved_at' => 'datetime',
            'rejected_at' => 'datetime',
            'invalidated_at' => 'datetime',
        ];
    }
}
