<?php

namespace Modules\User\Services\Account;

use Modules\User\Models\User;

interface AccountServiceInterface
{
    /**
     * Model for the resource.
     */
    public function model(): string;

    /**
     * Get a new instance of the model.
     */
    public function getModel(): User;

    /**
     * Get user information
     */
    public function me(): User;

    /**
     * Update the user's profile.
     */
    public function updateProfile(array $data): User;

    /**
     * Update the user's password.
     */
    public function updatePassword(array $data): bool;

    /**
     * Logout the user from all other sessions/devices except current.
     */
    public function logoutFromOtherDevices(User $user): void;
}
