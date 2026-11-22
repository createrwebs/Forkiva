<?php

namespace Modules\User\Http\Requests\Api\V1;

use Illuminate\Validation\ValidationException;
use Laravel\Passkeys\Support\WebAuthn;
use Modules\Core\Http\Requests\Request;
use Throwable;
use Webauthn\PublicKeyCredential;

abstract class PasskeyRequest extends Request
{
    protected PublicKeyCredential $publicKeyCredential;

    /**
     * Get the shared WebAuthn credential rules.
     */
    protected function credentialRules(): array
    {
        return [
            'credential' => ['required', 'array'],
            'credential.id' => ['required', 'string'],
            'credential.rawId' => ['required', 'string'],
            'credential.type' => ['required', 'string', 'in:public-key'],
            'credential.response' => ['required', 'array'],
        ];
    }

    /**
     * Get the deserialized browser credential.
     */
    public function credential(): PublicKeyCredential
    {
        return $this->publicKeyCredential;
    }

    /**
     * Deserialize the credential only after its transport shape is valid.
     */
    protected function passedValidation(): void
    {
        parent::passedValidation();

        try {
            $this->publicKeyCredential = WebAuthn::fromJson(
                json_encode($this->input('credential'), JSON_THROW_ON_ERROR),
                PublicKeyCredential::class
            );
        } catch (Throwable) {
            throw ValidationException::withMessages([
                'credential' => [__('user::auth.passkey_invalid')],
            ]);
        }
    }

    /** {@inheritDoc} */
    protected function availableAttributes(): string
    {
        return 'user::attributes.passkeys';
    }
}
