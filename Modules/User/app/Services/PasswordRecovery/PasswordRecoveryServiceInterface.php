<?php

namespace Modules\User\Services\PasswordRecovery;

use Illuminate\Cache\Repository;
use Illuminate\Validation\ValidationException;
use Random\RandomException;
use Throwable;

interface PasswordRecoveryServiceInterface
{
    public const TTL = 600;

    public const COOLDOWN = 60;

    /**
     * Use the configured cache store shared by recovery requests and queued delivery.
     */
    public function cache(): Repository;

    /**
     * Derive a namespaced cache key without exposing raw tokens or destinations.
     */
    public function key(string $value): string;

    /**
     * Create a hashed recovery challenge and queue encrypted delivery work.
     *
     * Destination-scoped limits also apply to decoy requests. A new challenge supersedes
     * any previous code or reset grant for the same channel and destination.
     *
     * @param  array{channel: 'email'|'sms', recovery_token: string}  $data
     * @return array{challenge_token: string, expires_in: int, resend_available_in: int}
     *
     * @throws RandomException
     */
    public function requestCode(array $data): array;

    /**
     * Consume a valid code and issue a reset grant with the challenge's remaining lifetime.
     *
     * @param  array{challenge_token: string, code: string}  $data
     * @return array{reset_token: string, expires_in: int}
     *
     * @throws ValidationException When the challenge or code is no longer valid.
     * @throws Throwable
     */
    public function verify(array $data): array;

    /**
     * Consume a reset grant or legacy code, change the password and emit its security event.
     *
     * @param  array{password: string, password_confirmation?: string, reset_token?: string, challenge_token?: string, code?: string}  $data
     *
     * @throws ValidationException When the grant or code is no longer valid.
     * @throws Throwable
     */
    public function reset(array $data): void;
}
