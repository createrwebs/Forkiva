<?php

namespace Modules\User\Services\TwoFactor;

use Modules\User\Enums\TwoFactorMethod;
use Modules\User\Models\User;
use Psr\SimpleCache\InvalidArgumentException;
use Random\RandomException;
use Throwable;

interface TwoFactorServiceInterface
{
    /**
     * Determine whether two-factor authentication is enabled application-wide.
     */
    public function enabled(): bool;

    /**
     * Determine whether this user must complete a second factor.
     */
    public function requiredFor(User $user): bool;

    /**
     * Create a short-lived login challenge.
     *
     * @throws RandomException
     * @throws InvalidArgumentException
     */
    public function createLoginChallenge(User $user, array $context = []): array;

    /**
     * Verify a login challenge and return its user and client context.
     *
     * @throws InvalidArgumentException
     * @throws Throwable
     */
    public function verifyLoginChallenge(string $token, string $code): array;

    /**
     * Resend a deliverable login challenge.
     *
     * @throws InvalidArgumentException
     * @throws RandomException
     */
    public function resendLoginChallenge(string $token): array;

    /**
     * Return the current account configuration and usable methods.
     */
    public function status(User $user): array;

    /**
     * Require a password and any enrolled factor, independently of administrator toggles.
     */
    public function confirmIdentity(User $user, string $currentPassword, ?string $code = null): void;

    /**
     * Start enrollment for one method.
     *
     * @throws RandomException
     */
    public function beginSetup(User $user, TwoFactorMethod $method, string $currentPassword, ?string $code = null): array;

    /**
     * Resend an email/SMS code for the current session's unexpired enrollment.
     */
    public function resendSetup(User $user): array;

    /**
     * Confirm enrollment and return new plaintext recovery codes.
     *
     * @throws InvalidArgumentException
     * @throws Throwable
     */
    public function confirmSetup(User $user, string $code): array;

    /**
     * Send a code for the user's configured email or SMS factor.
     *
     * @throws InvalidArgumentException
     * @throws RandomException
     */
    public function sendAccountCode(User $user): array;

    /**
     * Disable the user's second factor after verification.
     *
     * @throws InvalidArgumentException
     * @throws Throwable
     */
    public function disable(User $user, string $code, string $currentPassword): void;

    /**
     * Replace recovery codes after verifying the configured factor or a recovery code.
     *
     * @throws InvalidArgumentException
     * @throws Throwable
     */
    public function regenerateRecoveryCodes(User $user, string $code, string $currentPassword): array;
}
