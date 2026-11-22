<?php

namespace Modules\User\Services\Auth;

use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Laravel\Passkeys\Actions\GenerateRegistrationOptions;
use Laravel\Passkeys\Actions\StorePasskey;
use Laravel\Passkeys\Exceptions\InvalidPasskeyException;
use Laravel\Passkeys\Passkey;
use Modules\User\Models\User;
use Modules\User\Services\TwoFactor\TwoFactorServiceInterface;
use Webauthn\PublicKeyCredential;
use Webauthn\PublicKeyCredentialCreationOptions;

class PasskeyRegistrationService implements PasskeyRegistrationServiceInterface
{
    public function __construct(
        protected TwoFactorServiceInterface $twoFactor,
        protected PasskeyChallengeService $challenges,
        protected GenerateRegistrationOptions $generate,
        protected StorePasskey $storePasskey,
        protected AccountSecurityStateInterface $securityState,
    ) {}

    /** {@inheritDoc} */
    public function begin(User $user, string $currentPassword, ?string $code = null): PublicKeyCredentialCreationOptions
    {
        return DB::transaction(function () use ($user, $currentPassword, $code): PublicKeyCredentialCreationOptions {
            $user = $this->lockedUser($user);
            $this->securityState->sessionScope($user);
            $this->twoFactor->confirmIdentity($user, $currentPassword, $code);
            $options = ($this->generate)($user);
            $this->challenges->storeRegistration($user, $options);

            return $options;
        });
    }

    /** {@inheritDoc} */
    public function complete(User $user, string $name, PublicKeyCredential $credential): Passkey
    {
        return DB::transaction(function () use ($user, $name, $credential): Passkey {
            $user = $this->lockedUser($user);
            $options = $this->challenges->pullRegistration($user, $credential);

            try {
                return ($this->storePasskey)($user, $name, $credential, $options);
            } catch (InvalidPasskeyException) {
                throw ValidationException::withMessages([
                    'credential' => __('user::auth.passkey_invalid'),
                ]);
            }
        });
    }

    protected function lockedUser(User $user): User
    {
        $locked = User::query()->withoutGlobalActive()->lockForUpdate()->find($user->id);
        abort_unless($locked && $locked->is_active, 401, __('auth.failed'));
        if ($user->currentAccessToken()) {
            $locked->withAccessToken($user->currentAccessToken());
        }

        return $locked;
    }
}
