<?php

namespace Modules\User\Services\PasswordRecovery;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Modules\User\Models\User;
use Modules\User\Services\Auth\AccountSecurityState;
use Random\RandomException;

class PasswordRecoveryIdentity implements PasswordRecoveryIdentityInterface
{
    /**
     * Create an identity resolver bound to recovery settings and account security state.
     */
    public function __construct(
        protected PasswordRecoveryConfiguration $configuration,
        protected AccountSecurityState $security,
    ) {}

    /** {@inheritDoc}
     * @throws RandomException
     */
    public function identify(
        string $identifier,
        PasswordRecoveryServiceInterface $recovery,
    ): array {
        $channels = $this->configuration->channels();
        $normalized = Str::lower(trim($identifier));

        $matches = User::query()
            ->withoutGlobalActive()
            ->where(fn (Builder $query) => $query
                ->whereRaw('LOWER(email) = ?', [$normalized])
                ->orWhereRaw('LOWER(username) = ?', [$normalized]))
            ->limit(2)
            ->get();

        $user = $matches->count() === 1 ? $matches->first() : null;
        // Usernames may look like email addresses; only a unique, active owner may recover.
        if ($user && ! $user->is_active) {
            $user = null;
        }

        if ($user) {
            $channels = array_values(
                array_filter(
                    $channels,
                    fn ($channel) => $this->destination($user, $channel) !== null,
                ),
            );
        }

        // Unknown, inactive and ambiguous accounts get a decoy context,
        // never contact details.
        $token = bin2hex(random_bytes(32));

        $recovery->cache()->put(
            $recovery->key('identity:'.$token),
            [
                'user_id' => $user?->id,
                'fingerprint' => $user
                    ? $this->security->fingerprint($user)
                    : null,
                'channels' => $channels,
                'decoy_identity' => $recovery->key(
                    'unknown:'.$normalized,
                ),
            ],
            PasswordRecoveryServiceInterface::TTL,
        );

        return [
            'recovery_token' => $token,
            'channels' => $channels,
            'expires_in' => PasswordRecoveryServiceInterface::TTL,
        ];
    }

    /**
     * Read the account's stored contact, never a destination supplied by the requester.
     */
    private function destination(User $user, string $channel): ?string
    {
        return $channel === 'email' ? $user->email : $user->getRawOriginal('phone');
    }

    /** {@inheritDoc} */
    public function recipient(string $token, string $channel, PasswordRecoveryServiceInterface $recovery): array
    {
        $context = $recovery->cache()->get($recovery->key('identity:'.$token));

        if (! is_array($context) || ! in_array($channel, $context['channels'], true)) {
            throw ValidationException::withMessages([
                'identifier' => __('user::auth.recovery_restart'),
            ]);
        }

        $user = $context['user_id']
            ? User::query()
                ->withoutGlobalActive()
                ->find($context['user_id'])
            : null;

        if (
            $user
            && (! $user->is_active || ! hash_equals($context['fingerprint'], $this->security->fingerprint($user)))
        ) {
            $user = null;
        }

        $destination = $user
            ? $this->destination($user, $channel)
            : null;

        return [
            $destination ? $user : null,
            $destination ?? $context['decoy_identity'],
        ];
    }
}
