<?php

namespace Modules\Notification\Services\NotificationRecipient;

use Illuminate\Support\Collection;
use Modules\User\Models\User;

interface NotificationRecipientRoleResolverServiceInterface
{
    /**
     * Get available recipient roles.
     *
     * @return array<int, array<string, mixed>>
     */
    public function all(): array;

    /**
     * Get available recipient role keys.
     *
     * @return array<int, string>
     */
    public function keys(): array;

    /**
     * Determine if the role key exists.
     */
    public function exists(string $role): bool;

    /**
     * Get recipient role keys assigned to the user.
     *
     * @return array<int, string>
     */
    public function keysFor(User $user): array;

    /**
     * Preload profile-derived contexts for a group of users.
     *
     * @param  Collection<int, User>  $users
     */
    public function preloadContextsFor(Collection $users): void;

    /**
     * Get profile-derived notification contexts for the user.
     *
     * These contexts are intentionally separate from assigned roles and are not
     * exposed as workflow recipient settings.
     *
     * @return array<int, string>
     */
    public function contextsFor(User $user): array;
}
