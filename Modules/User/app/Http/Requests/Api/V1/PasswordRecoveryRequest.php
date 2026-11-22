<?php

namespace Modules\User\Http\Requests\Api\V1;

use Modules\Core\Http\Requests\Request;

class PasswordRecoveryRequest extends Request
{
    /**
     * Validate the selected channel and required account recovery context.
     */
    public function rules(): array
    {
        return [
            'channel' => ['required', 'in:email,sms'],
            'recovery_token' => [
                'required',
                'string',
                'size:64',
                'regex:/^[a-f0-9]+$/',
            ],
        ];
    }

    /** {@inheritDoc} */
    protected function availableAttributes(): string
    {
        return 'user::attributes.password_recovery';
    }
}
