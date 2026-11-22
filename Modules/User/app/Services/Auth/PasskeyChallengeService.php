<?php

namespace Modules\User\Services\Auth;

use Illuminate\Contracts\Cache\Repository;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Config;
use Illuminate\Validation\ValidationException;
use Laravel\Passkeys\Passkeys;
use Laravel\Passkeys\Support\WebAuthn;
use Modules\User\Models\User;
use Webauthn\PublicKeyCredential;
use Webauthn\PublicKeyCredentialCreationOptions;
use Webauthn\PublicKeyCredentialRequestOptions;

class PasskeyChallengeService
{
    public function __construct(protected AccountSecurityStateInterface $securityState) {}

    /**
     * Store passwordless login options for one short-lived ceremony.
     */
    public function storeLogin(PublicKeyCredentialRequestOptions $options): void
    {
        $this->store('login', $options->challenge, WebAuthn::toJson($options));
    }

    /**
     * Consume passwordless login options for the credential challenge.
     */
    public function pullLogin(PublicKeyCredential $credential): PublicKeyCredentialRequestOptions
    {
        return WebAuthn::fromJson(
            $this->pull('login', $this->challenge($credential)),
            PublicKeyCredentialRequestOptions::class
        );
    }

    /**
     * Store registration options bound to the authenticated user.
     */
    public function storeRegistration(
        User $user,
        PublicKeyCredentialCreationOptions $options
    ): void {
        $this->store('registration', $options->challenge, json_encode([
            'options' => WebAuthn::toJson($options),
            'fingerprint' => $this->securityState->fingerprint($user),
            'session' => $this->securityState->sessionScope($user),
        ], JSON_THROW_ON_ERROR), $user->id);
    }

    /**
     * Consume registration options bound to the authenticated user.
     */
    public function pullRegistration(
        User $user,
        PublicKeyCredential $credential
    ): PublicKeyCredentialCreationOptions {
        $approval = json_decode($this->pull('registration', $this->challenge($credential), $user->id), true);
        if (! is_array($approval)
            || ! is_string($approval['options'] ?? null)
            || ! is_string($approval['fingerprint'] ?? null)
            || ! is_string($approval['session'] ?? null)
            || ! hash_equals($this->securityState->fingerprint($user), $approval['fingerprint'])
            || ! hash_equals($this->securityState->sessionScope($user), $approval['session'])) {
            throw ValidationException::withMessages([
                'credential' => __('user::auth.passkey_expired'),
            ]);
        }

        return WebAuthn::fromJson(
            $approval['options'],
            PublicKeyCredentialCreationOptions::class
        );
    }

    /**
     * Cache a ceremony only slightly longer than its browser timeout.
     */
    private function store(
        string $purpose,
        string $challenge,
        string $serialized,
        ?int $userId = null
    ): void {
        $seconds = (int) ceil(Passkeys::timeout() / 1000) + 30;
        if ($purpose === 'registration') {
            $seconds = min(300, max(1, $seconds));
        }

        $this->cache()->put(
            $this->cacheKey($purpose, $challenge, $userId),
            $serialized,
            now()->addSeconds($seconds)
        );
    }

    /**
     * Consume a ceremony so its challenge cannot be replayed.
     */
    private function pull(string $purpose, string $challenge, ?int $userId = null): string
    {
        $serialized = $this->cache()->pull(
            $this->cacheKey($purpose, $challenge, $userId)
        );

        if (! is_string($serialized)) {
            throw ValidationException::withMessages([
                'credential' => [__('user::auth.passkey_expired')],
            ]);
        }

        return $serialized;
    }

    /**
     * Use storage that persists between the two HTTP requests in a ceremony.
     */
    private function cache(): Repository
    {
        return Cache::store(Config::string('passkeys.cache_store'));
    }

    /**
     * Read the binary challenge parsed by the WebAuthn serializer.
     */
    private function challenge(PublicKeyCredential $credential): string
    {
        return $credential->response->clientDataJSON->challenge;
    }

    /**
     * Keep raw challenges and user identifiers out of cache keys.
     */
    private function cacheKey(string $purpose, string $challenge, ?int $userId): string
    {
        return implode(':', [
            'passkeys',
            $purpose,
            $userId ?? 'guest',
            hash('sha256', $challenge),
        ]);
    }
}
