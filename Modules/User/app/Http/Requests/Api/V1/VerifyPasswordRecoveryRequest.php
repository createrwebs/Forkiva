<?php

namespace Modules\User\Http\Requests\Api\V1;

use Modules\Core\Http\Requests\Request;

class VerifyPasswordRecoveryRequest extends Request
{
    /**
     * Validate the challenge token and six-digit recovery code.
     */
    public function rules(): array
    {
        return [
            'challenge_token' => ['required', 'string', 'size:64', 'regex:/^[a-f0-9]+$/'],
            'code' => ['required', 'string', 'regex:/^[0-9]{6}$/'],
        ];
    }

    /** {@inheritDoc} */
    protected function availableAttributes(): string
    {
        return 'user::attributes.password_recovery';
    }
}
