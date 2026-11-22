<?php

namespace Modules\User\Services\Auth;

use Illuminate\Support\Facades\Config;
use Illuminate\Validation\ValidationException;
use Laravel\Sanctum\PersonalAccessToken;
use Modules\User\Models\User;

class AccountSecurityState implements AccountSecurityStateInterface
{
    /** {@inheritDoc} */
    public function fingerprint(User $user): string
    {
        return hash_hmac('sha256', serialize([
            $user->password,
            $user->email,
            $user->phone,
            $user->phone_country_iso_code,
            $user->two_factor_method?->value,
            $user->getRawOriginal('two_factor_secret'),
            $user->getRawOriginal('two_factor_recovery_codes'),
            $user->getRawOriginal('two_factor_confirmed_at'),
        ]), Config::string('app.key'));
    }

    /** {@inheritDoc} */
    public function sessionScope(User $user): string
    {
        $token = $user->currentAccessToken();
        if ($token instanceof PersonalAccessToken) {
            return 'token:'.$token->getKey();
        }
        if (request()->hasSession()) {
            return 'session:'.hash('sha256', request()->session()->getId());
        }

        throw ValidationException::withMessages([
            'credential' => __('user::auth.passkey_expired'),
        ]);
    }
}
