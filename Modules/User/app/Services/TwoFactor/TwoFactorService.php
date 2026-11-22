<?php

namespace Modules\User\Services\TwoFactor;

use Illuminate\Contracts\Cache\Repository;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Laravel\Sanctum\PersonalAccessToken;
use Modules\Sms\Services\SmsProvider\SmsProviderConfiguratorServiceInterface;
use Modules\Support\Enums\Mailer;
use Modules\User\Enums\TwoFactorMethod;
use Modules\User\Models\User;
use Modules\User\Notifications\TwoFactorCodeNotification;
use Modules\User\Services\Auth\AccountSecurityStateInterface;
use Psr\SimpleCache\InvalidArgumentException;
use Random\RandomException;
use Throwable;

class TwoFactorService implements TwoFactorServiceInterface
{
    /**
     * Create a new two-factor service instance.
     */
    public function __construct(
        protected SmsProviderConfiguratorServiceInterface $smsProvider,
        protected TwoFactorAttemptLimiter $attempts,
        protected AccountSecurityStateInterface $securityState,
    ) {}

    /** {@inheritDoc} */
    public function createLoginChallenge(User $user, array $context = []): array
    {
        return $this->withLockedUser($user, function (User $user) use ($context): array {
            abort_unless($this->requiredFor($user), 422, __('user::auth.two_factor_not_enabled'));

            $token = Str::random(64);
            $payload = [
                'user_id' => $user->id,
                'method' => $user->two_factor_method->value,
                'context' => $context,
                'fingerprint' => $this->fingerprint($user),
                'expires_at' => now()->addMinutes($this->ttlMinutes())->getTimestamp(),
                'delivery_failed' => false,
            ];

            $this->cache()->put($this->loginChallengeKey($token), $payload, now()->addMinutes($this->ttlMinutes()));

            if ($this->isDeliverable($user->two_factor_method)) {
                // Reserve the per-account cooldown before delivery, including failed sends.
                // Starting another login must not bypass the resend cooldown.
                $payload['delivery_failed'] = true;
                if ($this->accountResendCooldownRemaining($user) === 0) {
                    $this->startAccountResendCooldown($user);
                    try {
                        $this->sendCode($user, $user->two_factor_method, $this->loginCodeKey($token));
                        $payload['delivery_failed'] = false;
                    } catch (Throwable) {
                        // Never log provider exceptions: they may contain the OTP or credentials.
                        // Recovery verification remains available even when delivery is down.
                        $this->cache()->forget($this->loginCodeKey($token));
                    }
                }
                $this->cache()->put($this->loginChallengeKey($token), $payload, now()->addMinutes($this->ttlMinutes()));
            }

            return $this->challengePayload($token, $user, $user->two_factor_method);
        });
    }

    /**
     * Serialize factor mutations and OTP consumption on the authoritative user row.
     */
    protected function withLockedUser(User $user, callable $callback): mixed
    {
        return DB::transaction(function () use ($user, $callback): mixed {
            $lockedUser = User::query()->withoutGlobalActive()->lockForUpdate()->find($user->id);
            abort_unless($lockedUser && $lockedUser->is_active, 422, __('user::auth.two_factor_challenge_expired'));
            if ($user->currentAccessToken()) {
                $lockedUser->withAccessToken($user->currentAccessToken());
            }

            $result = $callback($lockedUser);
            $user->setRawAttributes($lockedUser->getAttributes(), true);

            return $result;
        });
    }

    /** {@inheritDoc} */
    public function requiredFor(User $user): bool
    {
        return $this->enabled()
            && $user->two_factor_confirmed_at !== null
            && $user->two_factor_method !== null;
    }

    /** {@inheritDoc} */
    public function enabled(): bool
    {
        return (bool) setting('two_factor_enabled', false);
    }

    /**
     * Determine whether an administrator enabled a method.
     */
    protected function methodEnabled(TwoFactorMethod $method): bool
    {
        return (bool) setting("two_factor_{$method->value}_enabled", $method === TwoFactorMethod::Authenticator);
    }

    /**
     * Bind pending authorization to the credentials and factor that authorized it.
     */
    protected function fingerprint(User $user): string
    {
        return $this->securityState->fingerprint($user);
    }

    /**
     * Resolve challenge expiry.
     */
    protected function ttlMinutes(): int
    {
        return (int) config('user.two_factor.challenge_ttl_minutes', 10);
    }

    /**
     * Use storage that persists between the requests in a two-factor flow.
     */
    protected function cache(): Repository
    {
        return Cache::store(Config::string('user.two_factor.cache_store'));
    }

    /**
     * Login challenge cache key.
     */
    protected function loginChallengeKey(string $token): string
    {
        return 'two-factor:login:'.hash('sha256', $token);
    }

    /**
     * Determine whether the factor sends a code.
     */
    protected function isDeliverable(TwoFactorMethod $method): bool
    {
        return in_array($method, [TwoFactorMethod::Email, TwoFactorMethod::Sms], true);
    }

    /**
     * Return the seconds remaining before another account code can be sent.
     *
     * @throws InvalidArgumentException
     */
    protected function accountResendCooldownRemaining(User $user): int
    {
        $availableAt = $this->cache()->get($this->accountResendCooldownKey($user));

        return is_int($availableAt) ? max(0, $availableAt - now()->getTimestamp()) : 0;
    }

    /**
     * Account Resend cooldown Key
     */
    protected function accountResendCooldownKey(User $user): string
    {
        return 'two-factor:account-resend-cooldown:'.$user->id;
    }

    /**
     * Start the authenticated account code resend cooldown.
     */
    protected function startAccountResendCooldown(User $user): void
    {
        $seconds = $this->resendCooldownSeconds();
        $this->cache()->put(
            $this->accountResendCooldownKey($user),
            now()->addSeconds($seconds)->getTimestamp(),
            now()->addSeconds($seconds),
        );
    }

    /**
     * Resolve the delay between login challenge resends.
     */
    protected function resendCooldownSeconds(): int
    {
        return max(1, (int) config('user.two_factor.resend_cooldown_seconds', 60));
    }

    /**
     * Send and cache a fresh email or SMS code.
     *
     * @throws RandomException
     */
    protected function sendCode(User $user, TwoFactorMethod $method, string $cacheKey): void
    {
        abort_unless($this->methodAvailable($method), 422, __('user::auth.two_factor_method_unavailable'));
        $this->ensureDestinationAvailable($user, $method);

        $code = $this->generateCode();
        $this->notifyCode($user, $method, $code);
        $this->cache()->put($cacheKey, [
            'hash' => Hash::make($code),
            'fingerprint' => $this->fingerprint($user),
        ], now()->addMinutes($this->ttlMinutes()));
    }

    /**
     * Determine whether the method and its delivery service are enabled.
     */
    protected function methodAvailable(TwoFactorMethod $method): bool
    {
        if (! $this->methodEnabled($method)) {
            return false;
        }

        return match ($method) {
            TwoFactorMethod::Authenticator => true,
            TwoFactorMethod::Email => $this->emailProviderAvailable(),
            TwoFactorMethod::Sms => $this->smsProvider->activeProvider() !== null,
        };
    }

    /**
     * Determine whether the configured email delivery service has a valid mailer.
     */
    protected function emailProviderAvailable(): bool
    {
        return filter_var(
            setting('services.notifications.email.enabled', false),
            FILTER_VALIDATE_BOOLEAN,
        ) && Mailer::tryFrom((string) setting('services.notifications.email.mailer', '')) !== null;
    }

    /**
     * Ensure the account has a destination for the selected delivery method.
     */
    protected function ensureDestinationAvailable(User $user, TwoFactorMethod $method): void
    {
        $message = match ($method) {
            TwoFactorMethod::Email => blank($user->email)
                ? __('user::auth.two_factor_email_destination_missing')
                : null,
            TwoFactorMethod::Sms => blank($user->routeNotificationForSms())
                ? __('user::auth.two_factor_sms_destination_missing')
                : null,
            TwoFactorMethod::Authenticator => null,
        };

        if ($message) {
            throw ValidationException::withMessages(['method' => $message]);
        }
    }

    /**
     * Generate a random numeric code.
     *
     * @throws RandomException
     */
    protected function generateCode(): string
    {
        $digits = (int) config('user.two_factor.code_digits', 6);

        return str_pad((string) random_int(0, (10 ** $digits) - 1), $digits, '0', STR_PAD_LEFT);
    }

    /**
     * Provider failures must not place codes or transport credentials in exception logs.
     */
    protected function notifyCode(User $user, TwoFactorMethod $method, string $code, string $errorField = 'code'): void
    {
        try {
            $user->notify(new TwoFactorCodeNotification($code, $method, $this->ttlMinutes()));
        } catch (Throwable) {
            Log::warning('Two-factor code delivery failed.', ['method' => $method->value]);
            throw ValidationException::withMessages([
                $errorField => __('user::auth.two_factor_delivery_failed'),
            ]);
        }
    }

    /**
     * Login code key
     */
    protected function loginCodeKey(string $token): string
    {
        return 'two-factor:login-code:'.hash('sha256', $token);
    }

    /**
     * Create the public login challenge response.
     *
     * @throws InvalidArgumentException
     */
    protected function challengePayload(string $token, User $user, TwoFactorMethod $method): array
    {
        $payload = $this->cache()->get($this->loginChallengeKey($token), []);

        return [
            'two_factor_required' => true,
            'challenge_token' => $token,
            'method' => $method->value,
            'destination' => $this->maskedDestination($user, $method),
            'can_resend' => $this->isDeliverable($method) && $this->methodAvailable($method),
            'resend_available_in' => $this->accountResendCooldownRemaining($user),
            'expires_in' => max(0, ($payload['expires_at'] ?? 0) - now()->getTimestamp()),
            'delivery_failed' => (bool) ($payload['delivery_failed'] ?? false),
        ];
    }

    /**
     * Mask the destination returned to clients.
     */
    protected function maskedDestination(User $user, TwoFactorMethod $method): ?string
    {
        if ($method === TwoFactorMethod::Authenticator) {
            return null;
        }

        $destination = $method === TwoFactorMethod::Email
            ? (string) $user->email
            : (string) $user->routeNotificationForSms();

        if ($method === TwoFactorMethod::Email && str_contains($destination, '@')) {
            [$name, $domain] = explode('@', $destination, 2);

            return Str::substr($name, 0, 1).str_repeat('*', max(2, Str::length($name) - 1)).'@'.$domain;
        }

        $digits = preg_replace('/\D+/', '', $destination) ?: '';

        return $digits === '' ? null : str_repeat('*', max(0, strlen($digits) - 4)).substr($digits, -4);
    }

    /** {@inheritDoc} */
    public function confirmIdentity(User $user, string $currentPassword, ?string $code = null): void
    {
        $this->withLockedUser($user, function (User $user) use ($currentPassword, $code): void {
            $this->ensurePassword($user, $currentPassword);
            if ($this->enrolled($user)) {
                $this->ensureCurrentFactor($user, $code ?? '');
            }
        });
    }

    /**
     * Ensure a sensitive account change is authorized by the current password.
     */
    protected function ensurePassword(User $user, string $currentPassword): void
    {
        $identity = 'password:'.$user->id;
        $this->attempts->hit($identity);
        if (! Hash::check($currentPassword, $user->password)) {
            throw ValidationException::withMessages([
                'current_password' => __('user::messages.current_password_incorrect'),
            ]);
        }
        $this->attempts->clear($identity);
    }

    protected function enrolled(User $user): bool
    {
        return $user->two_factor_method !== null && $user->two_factor_confirmed_at !== null;
    }

    /**
     * Authorize replacement with an existing factor, including when its provider is down.
     *
     * @throws Throwable
     */
    protected function ensureCurrentFactor(User $user, string $code): void
    {
        if (! $this->verifyForUser($user, $code, $this->accountCodeKey($user), true)) {
            throw ValidationException::withMessages([
                'code' => __('user::auth.two_factor_code_invalid'),
            ]);
        }
    }

    /**
     * Verify an authenticator, delivered, or recovery code for the user.
     *
     * @throws Throwable
     */
    protected function verifyForUser(
        User $user,
        string $code,
        string $deliveryCacheKey,
        bool $allowRecoveryCode,
    ): bool {
        return $this->withLockedUser($user, function (User $user) use ($code, $deliveryCacheKey, $allowRecoveryCode): bool {
            $identity = 'verification:'.$user->id;
            $this->attempts->hit($identity);
            $method = $user->two_factor_method;

            if ($method === TwoFactorMethod::Authenticator) {
                $timestep = $this->verifyTotp((string) $user->two_factor_secret, $code);
                if ($timestep !== null && $timestep > ($user->two_factor_last_used_timestep ?? -1)) {
                    $user->forceFill(['two_factor_last_used_timestep' => $timestep])->save();
                    $this->attempts->clear($identity);

                    return true;
                }
            } elseif ($method) {
                $delivery = $this->cache()->get($deliveryCacheKey);
                if (is_array($delivery)
                    && hash_equals($this->fingerprint($user), $delivery['fingerprint'] ?? '')
                    && Hash::check($this->normalizeCode($code), $delivery['hash'] ?? '')) {
                    $this->cache()->forget($deliveryCacheKey);
                    $this->attempts->clear($identity);

                    return true;
                }
            }

            $valid = $allowRecoveryCode && $this->consumeRecoveryCode($user, $code);
            if ($valid) {
                $this->attempts->clear($identity);
            }

            return $valid;
        });
    }

    /**
     * Verify a TOTP and return the accepted timestep.
     */
    protected function verifyTotp(string $secret, string $code): ?int
    {
        $normalized = $this->normalizeCode($code);
        $period = (int) config('user.two_factor.totp_period', 30);
        $window = (int) config('user.two_factor.totp_window', 1);
        $timestep = (int) floor(now()->getTimestamp() / $period);

        for ($offset = -$window; $offset <= $window; $offset++) {
            $candidateTimestep = $timestep + $offset;
            if (hash_equals($this->totpAt($secret, $candidateTimestep), $normalized)) {
                return $candidateTimestep;
            }
        }

        return null;
    }

    /**
     * Normalize a numeric code.
     */
    protected function normalizeCode(string $code): string
    {
        return preg_replace('/\s+/', '', trim($code)) ?: '';
    }

    /**
     * Calculate an RFC 6238 code for one timestep.
     */
    protected function totpAt(string $secret, int $timestep): string
    {
        $counter = pack('N2', 0, $timestep);
        $hash = hash_hmac('sha1', $counter, $this->base32Decode($secret), true);
        $offset = ord($hash[19]) & 0x0F;
        $binary = ((ord($hash[$offset]) & 0x7F) << 24)
            | ((ord($hash[$offset + 1]) & 0xFF) << 16)
            | ((ord($hash[$offset + 2]) & 0xFF) << 8)
            | (ord($hash[$offset + 3]) & 0xFF);
        $digits = (int) config('user.two_factor.code_digits', 6);

        return str_pad((string) ($binary % (10 ** $digits)), $digits, '0', STR_PAD_LEFT);
    }

    /**
     * Decode unpadded Base32 bytes.
     */
    protected function base32Decode(string $value): string
    {
        $alphabet = array_flip(str_split('ABCDEFGHIJKLMNOPQRSTUVWXYZ234567'));
        $bits = '';
        foreach (str_split(Str::upper($value)) as $character) {
            if (isset($alphabet[$character])) {
                $bits .= str_pad(decbin($alphabet[$character]), 5, '0', STR_PAD_LEFT);
            }
        }

        $decoded = '';
        foreach (str_split($bits, 8) as $chunk) {
            if (strlen($chunk) === 8) {
                $decoded .= chr(bindec($chunk));
            }
        }

        return $decoded;
    }

    /**
     * Atomically consume one recovery code.
     *
     * @throws Throwable
     */
    protected function consumeRecoveryCode(User $user, string $code): bool
    {
        $normalized = Str::upper(trim($code));

        return DB::transaction(function () use ($user, $normalized): bool {
            $lockedUser = User::query()->withoutGlobalActive()->lockForUpdate()->find($user->id);
            if (! $lockedUser) {
                return false;
            }

            $codes = $lockedUser->two_factor_recovery_codes ?? [];
            foreach ($codes as $index => $hash) {
                if (Hash::check($normalized, $hash)) {
                    unset($codes[$index]);
                    $lockedUser->forceFill(['two_factor_recovery_codes' => array_values($codes)])->save();
                    $user->setRawAttributes($lockedUser->getAttributes(), true);

                    return true;
                }
            }

            return false;
        });
    }

    /**
     * Account code key
     */
    protected function accountCodeKey(User $user): string
    {
        return 'two-factor:account-code:'.$user->id;
    }

    /** {@inheritDoc} */
    public function verifyLoginChallenge(string $token, string $code): array
    {
        $payload = $this->cache()->get($this->loginChallengeKey($token));
        abort_unless(is_array($payload), 422, __('user::auth.two_factor_challenge_expired'));

        $user = User::query()
            ->withTrashed()
            ->withoutGlobalActive()
            ->find($payload['user_id']);
        abort_unless($user && $this->requiredFor($user), 422, __('user::auth.two_factor_challenge_expired'));

        return $this->withLockedUser($user, function (User $user) use ($token, $code): array {
            // Re-read under the row lock so simultaneous requests cannot reuse a challenge.
            $payload = $this->cache()->get($this->loginChallengeKey($token));
            abort_unless(
                is_array($payload) && hash_equals($this->fingerprint($user), $payload['fingerprint'] ?? ''),
                422,
                __('user::auth.two_factor_challenge_expired'),
            );
            $this->attempts->hit('challenge:'.$token, $this->ttlMinutes() * 60);

            $valid = $this->verifyForUser(
                $user,
                $code,
                $this->loginCodeKey($token),
                allowRecoveryCode: true,
            );
            abort_unless($valid, 422, __('user::auth.two_factor_code_invalid'));

            $this->cache()->forget($this->loginChallengeKey($token));
            $this->cache()->forget($this->loginCodeKey($token));

            return [
                'user' => $user,
                'context' => is_array($payload['context'] ?? null) ? $payload['context'] : [],
            ];
        });
    }

    /** {@inheritDoc} */
    public function resendLoginChallenge(string $token): array
    {
        $payload = $this->cache()->get($this->loginChallengeKey($token));
        abort_unless(is_array($payload), 422, __('user::auth.two_factor_challenge_expired'));

        $user = User::query()->withTrashed()->withoutGlobalActive()->find($payload['user_id']);
        abort_unless($user, 422, __('user::auth.two_factor_challenge_expired'));

        return $this->withLockedUser($user, function (User $user) use ($token): array {
            $payload = $this->cache()->get($this->loginChallengeKey($token));
            abort_unless(
                is_array($payload) && $this->requiredFor($user)
                    && hash_equals($this->fingerprint($user), $payload['fingerprint'] ?? ''),
                422,
                __('user::auth.two_factor_challenge_expired'),
            );
            $method = $user->two_factor_method;
            abort_unless($this->isDeliverable($method), 422, __('user::auth.two_factor_resend_unavailable'));
            abort_if(
                $this->accountResendCooldownRemaining($user) > 0,
                429,
                __('user::auth.two_factor_resend_wait'),
            );

            $this->startAccountResendCooldown($user);
            try {
                $this->sendCode($user, $method, $this->loginCodeKey($token));
                $payload['delivery_failed'] = false;
            } catch (Throwable) {
                $payload['delivery_failed'] = true;
                $this->cache()->forget($this->loginCodeKey($token));
            }
            $this->cache()->put(
                $this->loginChallengeKey($token),
                $payload,
                max(1, $payload['expires_at'] - now()->getTimestamp()),
            );

            return $this->challengePayload($token, $user, $method);
        });
    }

    /** {@inheritDoc} */
    public function beginSetup(User $user, TwoFactorMethod $method, string $currentPassword, ?string $code = null): array
    {
        return $this->withLockedUser($user, function (User $user) use ($method, $currentPassword, $code): array {
            $this->ensurePassword($user, $currentPassword);
            abort_unless($this->enabled(), 422, __('user::auth.two_factor_disabled'));
            abort_unless($this->methodAvailable($method), 422, __('user::auth.two_factor_method_unavailable'));
            $this->ensureDestinationAvailable($user, $method);

            if ($this->enrolled($user)) {
                $this->ensureCurrentFactor($user, $code ?? '');
            }

            $setup = [
                'method' => $method->value,
                'fingerprint' => $this->fingerprint($user),
                'expires_at' => now()->addMinutes($this->ttlMinutes())->getTimestamp(),
            ];
            $secret = null;
            if ($method === TwoFactorMethod::Authenticator) {
                $secret = $this->generateSecret();
                $setup['secret'] = Crypt::encryptString($secret);
            }

            if ($this->isDeliverable($method)) {
                $this->startSetupResendCooldown($user);
                $newCode = $this->generateCode();
                $setup['code_hash'] = Hash::make($newCode);
                $this->notifyCode($user, $method, $newCode, 'method');
            }
            $this->cache()->put($this->setupKey($user), $setup, now()->setTimestamp($setup['expires_at']));

            return array_filter([
                'method' => $method->value,
                'secret' => $secret,
                'otpauth_uri' => $secret ? $this->otpAuthUri($user, $secret) : null,
                'destination' => $this->maskedDestination($user, $method),
                'expires_in' => $this->ttlMinutes() * 60,
                'resend_available_in' => $this->isDeliverable($method)
                    ? $this->setupResendCooldownRemaining($user)
                    : 0,
            ], fn (mixed $value) => $value !== null);
        });
    }

    /** {@inheritDoc} */
    public function resendSetup(User $user): array
    {
        return $this->withLockedUser($user, function (User $user): array {
            $setup = $this->cache()->get($this->setupKey($user));
            abort_unless(
                is_array($setup)
                    && is_string($setup['fingerprint'] ?? null)
                    && hash_equals($this->fingerprint($user), $setup['fingerprint'])
                    && is_int($setup['expires_at'] ?? null)
                    && $setup['expires_at'] > now()->getTimestamp(),
                422,
                __('user::auth.two_factor_setup_expired'),
            );
            $method = TwoFactorMethod::tryFrom((string) ($setup['method'] ?? ''));
            abort_unless($method && $this->isDeliverable($method), 422, __('user::auth.two_factor_resend_unavailable'));
            abort_unless($this->enabled() && $this->methodAvailable($method), 422, __('user::auth.two_factor_method_unavailable'));
            $this->ensureDestinationAvailable($user, $method);
            $this->startSetupResendCooldown($user);

            $code = $this->generateCode();
            $this->notifyCode($user, $method, $code);
            $setup['code_hash'] = Hash::make($code);
            // Resending replaces the OTP, not the password/factor approval or its expiry.
            $this->cache()->put($this->setupKey($user), $setup, now()->setTimestamp($setup['expires_at']));

            return [
                'method' => $method->value,
                'destination' => $this->maskedDestination($user, $method),
                'expires_in' => max(0, $setup['expires_at'] - now()->getTimestamp()),
                'resend_available_in' => $this->setupResendCooldownRemaining($user),
            ];
        });
    }

    /**
     * Preserve the per-account send limit across enrollment restarts and sessions.
     */
    protected function setupResendCooldownRemaining(User $user): int
    {
        $availableAt = $this->cache()->get('two-factor:setup-send-cooldown:'.$user->id);

        // Older cache entries stored a boolean and still need to block resends until expiry.
        return is_int($availableAt)
            ? max(0, $availableAt - now()->getTimestamp())
            : ($availableAt ? $this->resendCooldownSeconds() : 0);
    }

    /**
     * Reserve the cooldown before delivery, including failed delivery attempts.
     */
    protected function startSetupResendCooldown(User $user): void
    {
        $remaining = $this->setupResendCooldownRemaining($user);
        abort_if($remaining > 0, 429, __('user::auth.two_factor_resend_wait'), [
            'Retry-After' => (string) $remaining,
        ]);
        $availableAt = now()->addSeconds($this->resendCooldownSeconds());
        $this->cache()->put('two-factor:setup-send-cooldown:'.$user->id, $availableAt->getTimestamp(), $availableAt);
    }

    /**
     * Generate an RFC 4648 Base32 secret.
     *
     * @throws RandomException
     */
    protected function generateSecret(): string
    {
        return $this->base32Encode(random_bytes(20));
    }

    /**
     * Encode bytes as unpadded Base32.
     */
    protected function base32Encode(string $value): string
    {
        $alphabet = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';
        $bits = '';
        foreach (str_split($value) as $character) {
            $bits .= str_pad(decbin(ord($character)), 8, '0', STR_PAD_LEFT);
        }

        $encoded = '';
        foreach (str_split($bits, 5) as $chunk) {
            $encoded .= $alphabet[bindec(str_pad($chunk, 5, '0'))];
        }

        return $encoded;
    }

    /**
     * Setup Key
     */
    protected function setupKey(User $user): string
    {
        $token = $user->currentAccessToken();
        $session = $token instanceof PersonalAccessToken ? $token->getKey() : 'session';

        return 'two-factor:setup:'.$user->id.':'.$session;
    }

    /**
     * Build an authenticator-app enrollment URI.
     */
    protected function otpAuthUri(User $user, string $secret): string
    {
        $issuer = (string) setting('app_name', config('app.name', 'Forkiva'));
        $label = rawurlencode($issuer.':'.($user->email ?: $user->username));

        return 'otpauth://totp/'.$label.'?'.http_build_query([
            'secret' => $secret,
            'issuer' => $issuer,
            'algorithm' => 'SHA1',
            'digits' => (int) config('user.two_factor.code_digits', 6),
            'period' => (int) config('user.two_factor.totp_period', 30),
        ], '', '&', PHP_QUERY_RFC3986);
    }

    /** {@inheritDoc} */
    public function confirmSetup(User $user, string $code): array
    {
        return $this->withLockedUser($user, function (User $user) use ($code): array {
            $setup = $this->cache()->get($this->setupKey($user));
            abort_unless(
                is_array($setup) && hash_equals($this->fingerprint($user), $setup['fingerprint'] ?? ''),
                422,
                __('user::auth.two_factor_setup_expired'),
            );

            $method = TwoFactorMethod::tryFrom((string) ($setup['method'] ?? ''));
            abort_unless($method !== null, 422, __('user::auth.two_factor_setup_expired'));
            abort_unless($this->enabled() && $this->methodAvailable($method), 422, __('user::auth.two_factor_method_unavailable'));
            $this->ensureDestinationAvailable($user, $method);
            $identity = 'setup:'.$user->id;
            $this->attempts->hit($identity);

            $secret = isset($setup['secret']) ? Crypt::decryptString($setup['secret']) : null;
            $timestep = $method === TwoFactorMethod::Authenticator ? $this->verifyTotp((string) $secret, $code) : null;
            $valid = $method === TwoFactorMethod::Authenticator
                ? $timestep !== null
                : Hash::check($this->normalizeCode($code), (string) ($setup['code_hash'] ?? ''));
            abort_unless($valid, 422, __('user::auth.two_factor_code_invalid'));

            $recoveryCodes = $this->generateRecoveryCodes();
            $user->forceFill([
                'two_factor_method' => $method,
                'two_factor_secret' => $secret,
                'two_factor_recovery_codes' => $this->hashRecoveryCodes($recoveryCodes),
                'two_factor_confirmed_at' => now(),
                'two_factor_last_used_timestep' => $timestep,
            ])->save();
            $this->attempts->clear($identity);
            $this->cache()->forget($this->setupKey($user));
            $this->cache()->forget($this->accountCodeKey($user));

            return [
                'recovery_codes' => $recoveryCodes,
                ...$this->status($user->refresh()),
            ];
        });
    }

    /**
     * Generate plaintext recovery codes.
     */
    protected function generateRecoveryCodes(): array
    {
        return collect(range(1, (int) config('user.two_factor.recovery_code_count', 8)))
            ->map(fn () => Str::upper(Str::random(5).'-'.Str::random(5)))
            ->all();
    }

    /**
     * Hash recovery codes before persistence.
     */
    protected function hashRecoveryCodes(array $codes): array
    {
        return array_map(fn (string $code) => Hash::make($code), $codes);
    }

    /** {@inheritDoc} */
    public function status(User $user): array
    {
        return [
            'available' => $this->enabled(),
            'enabled' => $this->requiredFor($user),
            'method' => $user->two_factor_method?->value,
            'confirmed_at' => $user->two_factor_confirmed_at?->toAtomString(),
            'recovery_codes_remaining' => count($user->two_factor_recovery_codes ?? []),
            'resend_available_in' => $this->accountResendCooldownRemaining($user),
            'methods' => collect(TwoFactorMethod::cases())
                ->map(fn (TwoFactorMethod $method) => $this->methodPayload($user, $method))
                ->values()
                ->all(),
        ];
    }

    /**
     * Return one method's global and account availability.
     */
    protected function methodPayload(User $user, TwoFactorMethod $method): array
    {
        return [
            'id' => $method->value,
            'enabled' => $this->methodEnabled($method),
            'available' => $this->methodAvailable($method),
            'destination' => $this->maskedDestination($user, $method),
        ];
    }

    /** {@inheritDoc} */
    public function sendAccountCode(User $user): array
    {
        return $this->withLockedUser($user, function (User $user): array {
            abort_unless($this->requiredFor($user), 422, __('user::auth.two_factor_not_enabled'));
            abort_unless($this->isDeliverable($user->two_factor_method), 422, __('user::auth.two_factor_resend_unavailable'));
            $remaining = $this->accountResendCooldownRemaining($user);
            abort_if(
                $remaining > 0,
                429,
                __('user::auth.two_factor_resend_wait'),
                ['Retry-After' => (string) $remaining],
            );

            $this->startAccountResendCooldown($user);
            $this->sendCode($user, $user->two_factor_method, $this->accountCodeKey($user));

            return [
                'method' => $user->two_factor_method->value,
                'destination' => $this->maskedDestination($user, $user->two_factor_method),
                'resend_available_in' => $this->accountResendCooldownRemaining($user),
                'expires_in' => $this->ttlMinutes() * 60,
            ];
        });
    }

    /** {@inheritDoc} */
    public function disable(User $user, string $code, string $currentPassword): void
    {
        $this->withLockedUser($user, function (User $user) use ($code, $currentPassword): void {
            $this->ensurePassword($user, $currentPassword);
            abort_unless($this->requiredFor($user), 422, __('user::auth.two_factor_not_enabled'));
            $this->ensureCurrentFactor($user, $code);

            $user->forceFill([
                'two_factor_method' => null,
                'two_factor_secret' => null,
                'two_factor_recovery_codes' => null,
                'two_factor_confirmed_at' => null,
                'two_factor_last_used_timestep' => null,
            ])->save();

            $this->cache()->forget($this->accountCodeKey($user));
            $this->cache()->forget($this->accountResendCooldownKey($user));
            $this->cache()->forget($this->setupKey($user));
        });
    }

    /** {@inheritDoc} */
    public function regenerateRecoveryCodes(User $user, string $code, string $currentPassword): array
    {
        return $this->withLockedUser($user, function (User $user) use ($code, $currentPassword): array {
            $this->ensurePassword($user, $currentPassword);
            abort_unless($this->requiredFor($user), 422, __('user::auth.two_factor_not_enabled'));
            $this->ensureCurrentFactor($user, $code);

            $recoveryCodes = $this->generateRecoveryCodes();
            $user->forceFill([
                'two_factor_recovery_codes' => $this->hashRecoveryCodes($recoveryCodes),
            ])->save();
            $this->cache()->forget($this->accountCodeKey($user));
            $this->cache()->forget($this->accountResendCooldownKey($user));

            return ['recovery_codes' => $recoveryCodes];
        });
    }
}
