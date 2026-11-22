<?php

namespace Modules\User\Services\Auth;

use Modules\Hr\Models\Employee;
use Modules\User\Models\User;

interface AuthServiceInterface
{
    /**
     * Login user
     */
    public function login(array $credentials): array;

    /**
     * Complete a pending two-factor login.
     */
    public function completeTwoFactorLogin(string $challengeToken, string $code): array;

    /**
     * Logout
     */
    public function logout(): bool;

    /**
     * Check if the identifier is email value
     */
    public function isEmail(string $identifier): bool;

    /**
     * Validate the user status
     */
    public function validateUserStatus(?User $user): void;

    /**
     * Validate the employee status
     */
    public function validateEmployeeStatus(?Employee $employee): void;

    /**
     * Grant user auth response
     */
    public function grantToken(User $user, bool $remember = false, string $name = 'Login'): array;

    /**
     * Validate employee access for employee app authentication.
     */
    public function validateEmployeeAccess(?User $user): void;

    /**
     * Validate driver access for driver app authentication.
     */
    public function validateDriverAccess(?User $user): void;
}
