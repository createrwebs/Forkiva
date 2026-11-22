<?php

namespace Modules\User\Services\PasswordRecovery;

use Illuminate\Cache\Repository;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Modules\User\Events\PasswordChanged;
use Modules\User\Jobs\SendPasswordRecoveryCode;
use Modules\User\Models\User;
use Modules\User\Services\Auth\AccountSecurityState;
use Throwable;

class PasswordRecoveryService implements PasswordRecoveryServiceInterface
{
    /**
     * Create a recovery service bound to delivery settings and account security state.
     */
    public function __construct(
        protected PasswordRecoveryConfiguration $configuration,
        protected AccountSecurityState $security,
    ) {}

    /** {@inheritDoc} */
    public function requestCode(array $data): array
    {
        abort_unless(
            in_array($data['channel'], $this->configuration->channels(), true),
            422,
            __('user::auth.recovery_unavailable'),
        );

        [$recipient, $destination] = app(PasswordRecoveryIdentityInterface::class)->recipient(
            $data['recovery_token'],
            $data['channel'],
            $this,
        );

        $identity = $this->key($data['channel'].':'.$destination);
        $token = bin2hex(random_bytes(32));
        $code = (string) random_int(100000, 999999);

        $this->cache()
            ->lock($identity.':lock', 15)
            ->block(5, function () use (
                $data,
                $recipient,
                $identity,
                $token,
                $code,
            ): void {
                abort_if(
                    $this->cache()->has($identity.':cooldown'),
                    429,
                    __('user::auth.recovery_wait'),
                    ['Retry-After' => (string) self::COOLDOWN],
                );

                $count = (int) $this->cache()->get($identity.':sends', 0);

                abort_if(
                    $count >= 5,
                    429,
                    __('user::auth.recovery_wait'),
                    ['Retry-After' => '3600'],
                );

                if ($count === 0) {
                    $this->cache()->put($identity.':sends', 0, 3600);
                }

                $this->cache()->increment($identity.':sends');

                $this->cache()->put(
                    $identity.':cooldown',
                    true,
                    self::COOLDOWN,
                );

                $user = $recipient;

                $this->cache()->put(
                    $this->key($token),
                    [
                        'user_id' => $user?->id,
                        'channel' => $data['channel'],
                        'identity' => $identity,
                        'fingerprint' => $user
                            ? $this->security->fingerprint($user)
                            : null,
                        'code_hash' => Hash::make($code),
                        'attempts' => 0,
                        'expires_at' => now()->getTimestamp() + self::TTL,
                    ],
                    self::TTL,
                );

                $this->cache()->put(
                    $identity.':current',
                    $this->key($token),
                    self::TTL,
                );
            });

        // Queue the same encrypted work for known and unknown destinations
        // to avoid delivery timing leaks.
        SendPasswordRecoveryCode::dispatch(
            $token,
            $code,
            app()->getLocale(),
        );

        return [
            'challenge_token' => $token,
            'expires_in' => self::TTL,
            'resend_available_in' => self::COOLDOWN,
        ];
    }

    /** {@inheritDoc} */
    public function key(string $value): string
    {
        return 'password-recovery:'.hash_hmac(
            'sha256',
            $value,
            config('app.key'),
        );
    }

    /** {@inheritDoc} */
    public function cache(): Repository
    {
        return Cache::store(config('user.password_recovery.cache_store'));
    }

    /** {@inheritDoc} */
    public function verify(array $data): array
    {
        $result = $this->consumeCode(
            $data,
            function (
                User $user,
                array $payload,
                string $key,
            ): array {
                $token = bin2hex(random_bytes(32));
                $ttl = $payload['expires_at'] - now()->getTimestamp();

                unset($payload['code_hash'], $payload['attempts']);

                $this->cache()->put(
                    $this->key('reset:'.$token),
                    [
                        ...$payload,
                        'challenge_key' => $key,
                    ],
                    $ttl,
                );

                return [
                    'reset_token' => $token,
                    'expires_in' => $ttl,
                ];
            },
        );

        if (! $result) {
            $this->invalidCode();
        }

        return $result;
    }

    /**
     * Verify a code under a cache lock and consume it after the callback succeeds.
     *
     * Failed checks count toward the five-attempt limit without extending expiry.
     *
     * @template T
     *
     * @param  array{challenge_token: string, code: string}  $data
     * @param  callable(User, array<string, mixed>, string): T  $onVerified
     * @return T|null
     *
     * @throws Throwable
     */
    private function consumeCode(array $data, callable $onVerified): mixed
    {
        $key = $this->key($data['challenge_token']);

        return $this->cache()
            ->lock($key.':lock', 30)
            ->block(5, function () use (
                $key,
                $data,
                $onVerified,
            ): mixed {
                $payload = $this->cache()->get($key);

                if (
                    ! is_array($payload)
                    || $payload['expires_at'] <= now()->getTimestamp()
                    || $payload['attempts'] >= 5
                ) {
                    return null;
                }

                $payload['attempts']++;

                $this->cache()->put(
                    $key,
                    $payload,
                    max(
                        1,
                        $payload['expires_at'] - now()->getTimestamp(),
                    ),
                );

                if (! Hash::check($data['code'], $payload['code_hash']) || ! $this->currentPayload($payload, $key)) {
                    return null;
                }

                return $this->updateAccount(
                    $payload,
                    function (User $user) use (
                        $onVerified,
                        $payload,
                        $key,
                    ): mixed {
                        $result = $onVerified(
                            $user,
                            $payload,
                            $key,
                        );

                        $this->cache()->forget($key);

                        return $result;
                    },
                );
            });
    }

    /**
     * Check expiry, account presence, channel availability and the latest challenge pointer.
     */
    private function currentPayload(mixed $payload, string $challengeKey): bool
    {
        return is_array($payload)
            && $payload['expires_at'] > now()->getTimestamp()
            && $payload['user_id']
            && in_array(
                $payload['channel'],
                $this->configuration->channels(),
                true,
            )
            && $this->cache()->get($payload['identity'].':current') === $challengeKey;
    }

    /**
     * Lock the account and recheck its active status and security fingerprint before mutation.
     *
     * @template T
     *
     * @param  array<string, mixed>  $payload
     * @param  callable(User): T  $callback
     * @return T|null
     *
     * @throws Throwable
     */
    private function updateAccount(array $payload, callable $callback): mixed
    {
        return DB::transaction(
            function () use ($payload, $callback): mixed {
                $user = User::query()
                    ->withoutGlobalActive()
                    ->lockForUpdate()
                    ->find($payload['user_id']);

                if (
                    ! $user
                    || ! $user->is_active
                    || ! hash_equals(
                        $payload['fingerprint'],
                        $this->security->fingerprint($user),
                    )
                ) {
                    return null;
                }

                return $callback($user);
            },
        );
    }

    /**
     * Reject invalid, expired or consumed recovery credentials with a uniform validation error.
     *
     * @throws ValidationException
     */
    private function invalidCode(): never
    {
        throw ValidationException::withMessages([
            'code' => __('user::auth.recovery_invalid'),
        ]);
    }

    /** {@inheritDoc} */
    public function reset(array $data): void
    {
        if (isset($data['reset_token'])) {
            $key = $this->key('reset:'.$data['reset_token']);

            $user = $this->cache()
                ->lock($key.':lock', 30)
                ->block(5, function () use ($key, $data): ?User {
                    $payload = $this->cache()->get($key);

                    if (
                        ! $this->currentPayload(
                            $payload,
                            $payload['challenge_key'] ?? '',
                        )
                    ) {
                        return null;
                    }

                    return $this->updateAccount(
                        $payload,
                        function (User $user) use ($key, $data): User {
                            $this->changePassword(
                                $user,
                                $data['password'],
                            );

                            $this->cache()->forget($key);

                            return $user;
                        },
                    );
                });
        } else {
            // Keep the existing API contract for clients that submit
            // the code with the password.
            $user = $this->consumeCode(
                $data,
                function (User $user) use ($data): User {
                    $this->changePassword($user, $data['password']);

                    return $user;
                },
            );
        }

        if (! $user) {
            $this->invalidCode();
        }

        event(new PasswordChanged($user, request()->ip(), now(), app()->getLocale()));
    }

    /**
     * Replace the password and remember token, revoke API tokens and delete database sessions.
     *
     * Existing two-factor authentication settings are preserved.
     */
    private function changePassword(User $user, string $password): void
    {
        $user->forceFill([
            'password' => Hash::make($password),
            'remember_token' => Str::random(60),
        ])->save();

        $user->tokens()->delete();

        if (config('session.driver') === 'database') {
            DB::connection(config('session.connection'))
                ->table(config('session.table', 'sessions'))
                ->where('user_id', $user->id)
                ->delete();
        }
    }
}
