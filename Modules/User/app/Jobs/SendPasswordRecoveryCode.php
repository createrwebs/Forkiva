<?php

namespace Modules\User\Jobs;

use Illuminate\Contracts\Queue\ShouldBeEncrypted;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Modules\User\Models\User;
use Modules\User\Notifications\PasswordRecoveryCodeNotification;
use Modules\User\Services\Auth\AccountSecurityState;
use Modules\User\Services\PasswordRecovery\PasswordRecoveryConfiguration;
use Modules\User\Services\PasswordRecovery\PasswordRecoveryServiceInterface;
use Throwable;

class SendPasswordRecoveryCode implements ShouldBeEncrypted, ShouldQueue
{
    use Queueable;

    public int $tries = 1;

    /**
     * Capture the challenge, plaintext code and locale for encrypted queue storage.
     */
    public function __construct(
        protected string $token,
        protected string $code,
        protected string $locale,
    ) {}

    /**
     * Recheck current settings and account state before sending a recovery code.
     *
     * Decoy and superseded challenges are skipped. Delivery failures invalidate
     * the challenge without logging provider exceptions that may expose
     * credentials or the code.
     */
    public function handle(
        PasswordRecoveryServiceInterface $service,
        PasswordRecoveryConfiguration $configuration,
        AccountSecurityState $security,
    ): void {
        $key = $service->key($this->token);
        $payload = $service->cache()->get($key);

        if (
            ! is_array($payload)
            || ! $payload['user_id']
            || $payload['expires_at'] <= now()->getTimestamp()
            || $service->cache()->get($payload['identity'].':current') !== $key
            || ! in_array(
                $payload['channel'],
                $configuration->channels(),
                true,
            )
        ) {
            return;
        }

        $user = User::query()
            ->withoutGlobalActive()
            ->find($payload['user_id']);

        if (
            ! $user
            || ! $user->is_active
            || ! hash_equals(
                $payload['fingerprint'],
                $security->fingerprint($user),
            )
        ) {
            return;
        }

        try {
            $notification = new PasswordRecoveryCodeNotification(
                $this->code,
                $payload['channel'],
            );

            $user->notify(
                $notification->locale($this->locale),
            );
        } catch (Throwable) {
            // Provider exceptions can contain credentials, recipients or the OTP;
            // never log them.
            $service->cache()->forget($key);
        }
    }
}
