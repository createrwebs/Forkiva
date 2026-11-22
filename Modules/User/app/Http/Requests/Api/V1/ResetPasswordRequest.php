<?php

namespace Modules\User\Http\Requests\Api\V1;

use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Modules\Core\Http\Requests\Request;

class ResetPasswordRequest extends Request
{
    /**
     * Require a confirmed password and mutually exclusive reset-token or code credentials.
     */
    public function rules(): array
    {
        return [
            'reset_token' => [
                'required_without:challenge_token',
                Rule::prohibitedIf($this->filled('challenge_token')), 'string', 'size:64', 'regex:/^[a-f0-9]+$/',
            ],
            'challenge_token' => [
                'required_without:reset_token',
                Rule::prohibitedIf($this->filled('reset_token')), 'string', 'size:64', 'regex:/^[a-f0-9]+$/',
            ],
            'code' => [
                'required_without:reset_token',
                Rule::prohibitedIf($this->filled('reset_token')), 'string', 'regex:/^[0-9]{6}$/',
            ],
            'password' => [
                'required',
                'string',
                Password::min(8)->max(20)->mixedCase()->numbers()->symbols(),
                'confirmed',
            ],
        ];
    }

    /** {@inheritDoc} */
    protected function availableAttributes(): string
    {
        return 'user::attributes.password_recovery';
    }
}
