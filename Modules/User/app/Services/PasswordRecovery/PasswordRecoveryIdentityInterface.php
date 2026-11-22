<?php

namespace Modules\User\Services\PasswordRecovery;

use Illuminate\Validation\ValidationException;
use Modules\User\Models\User;

interface PasswordRecoveryIdentityInterface
{
    /**
     * Resolve a username or email address and cache its eligible recovery methods.
     *
     * Unknown, inactive and ambiguous accounts receive a decoy context without contact details.
     *
     * @return array{recovery_token: string, channels: list<'email'|'sms'>, expires_in: int}
     */
    public function identify(string $identifier, PasswordRecoveryServiceInterface $recovery): array;

    /**
     * Resolve a cached recovery context and recheck the account security fingerprint.
     *
     * A missing or changed account returns a decoy destination for uniform send handling.
     *
     * @return array{0: User|null, 1: string}
     *
     * @throws ValidationException When the context expired or the channel was not offered.
     */
    public function recipient(string $token, string $channel, PasswordRecoveryServiceInterface $recovery): array;
}
