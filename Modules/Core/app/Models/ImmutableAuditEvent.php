<?php

namespace Modules\Core\Models;

use Illuminate\Database\Eloquent\Model as EloquentModel;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Carbon;
use LogicException;
use Modules\Branch\Models\Branch;
use Modules\Support\Eloquent\Model;
use Modules\User\Models\User;

/**
 * @property int $id
 * @property int|null $subject_id
 * @property string|null $subject_type
 * @property int|null $branch_id
 * @property int|null $actor_id
 * @property string $action
 * @property string $reason
 * @property array<string, mixed>|null $before
 * @property array<string, mixed>|null $after
 * @property array<string, mixed>|null $metadata
 * @property string|null $previous_hash
 * @property string $event_hash
 * @property Carbon $occurred_at
 * @property-read EloquentModel|null $subject
 * @property-read Branch|null $branch
 * @property-read User|null $actor
 */
class ImmutableAuditEvent extends Model
{
    /**
     * The model does not use mutable Eloquent timestamps.
     */
    public $timestamps = false;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'subject_id',
        'subject_type',
        'branch_id',
        'actor_id',
        'action',
        'reason',
        'before',
        'after',
        'metadata',
        'previous_hash',
        'event_hash',
        'occurred_at',
    ];

    /** {@inheritDoc} */
    protected function casts(): array
    {
        return [
            'before' => 'array',
            'after' => 'array',
            'metadata' => 'array',
            'occurred_at' => 'immutable_datetime',
        ];
    }

    /**
     * Protect hash-chained audit evidence from mutation.
     */
    protected static function booted(): void
    {
        static::updating(fn (): never => throw new LogicException(__('core::exceptions.immutable_audit_update_forbidden')));
        static::deleting(fn (): never => throw new LogicException(__('core::exceptions.immutable_audit_delete_forbidden')));
    }

    /**
     * Get the protected business record, when one was attached.
     */
    public function subject(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * Get the branch attached to the audit evidence.
     */
    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class)
            ->withoutGlobalActive()
            ->withTrashed();
    }

    /**
     * Get the user who triggered the audited action.
     */
    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_id')
            ->withOutGlobalBranchPermission()
            ->withoutGlobalActive()
            ->withTrashed();
    }
}
