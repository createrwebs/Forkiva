<?php

namespace Modules\Branch\Traits;

use Auth;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Branch\Models\Branch;
use Modules\Branch\Scopes\BranchPermissionScope;
use Modules\Support\Scopes\ActiveScope;
use Modules\User\Models\User;

/**
 * @property int|null $branch_id
 * @property-read  Branch|null $branch
 *
 * @method static Builder|static whereBranch(int $id)
 * @method static Builder|static withOutGlobalBranchPermission()
 */
trait HasBranch
{
    /**
     * The branch key.
     *
     * @var string
     */
    const BRANCH_COLUMN_NAME = 'branch_id';

    /**
     * Boot the branch ownership constraints.
     */
    public static function bootHasBranch(): void
    {
        static::addGlobalScope('branch_permission', function (Builder $builder): void {
            $guard = Auth::guard();

            if (! $guard->hasUser()) {
                return;
            }

            $user = $guard->user();

            if ($user instanceof User && $user->assignedToBranch()) {
                (new BranchPermissionScope($user))->apply($builder, $builder->getModel());
            }
        });

        static::creating(function ($model): void {
            $guard = Auth::guard();

            if (! $guard->hasUser()) {
                return;
            }

            $user = $guard->user();

            if ($user instanceof User && $user->assignedToBranch()) {
                $model->branch_id = $user->branch_id;
            }
        });
    }

    /**
     * Get a model branch
     */
    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class, self::BRANCH_COLUMN_NAME)
            ->withTrashed()
            ->withoutGlobalScope(ActiveScope::class);
    }

    /**
     * Scope a query to only include records a specific branch.
     */
    public function scopeWhereBranch(Builder $query, int $id): void
    {
        $query->where(static::BRANCH_COLUMN_NAME, $id);
    }

    /**
     * Scope a query without global branch permission scope.
     */
    public function scopeWithOutGlobalBranchPermission(Builder $query): void
    {
        $query->withOutGlobalScope('branch_permission');
    }
}
