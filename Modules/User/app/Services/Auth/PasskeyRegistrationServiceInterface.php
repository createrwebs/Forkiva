<?php

namespace Modules\User\Services\Auth;

use Laravel\Passkeys\Passkey;
use Modules\User\Models\User;
use Webauthn\PublicKeyCredential;
use Webauthn\PublicKeyCredentialCreationOptions;

interface PasskeyRegistrationServiceInterface
{
    /**
     * Authorize one registration with existing credentials before creating its challenge.
     */
    public function begin(User $user, string $currentPassword, ?string $code = null): PublicKeyCredentialCreationOptions;

    /**
     * Consume the approval and save the new credential under the same account lock.
     */
    public function complete(User $user, string $name, PublicKeyCredential $credential): Passkey;
}
