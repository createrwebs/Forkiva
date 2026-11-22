<?php

namespace Modules\Installer\Http\Requests;

use Illuminate\Validation\Rules\Password;
use Modules\Core\Http\Requests\Request;
use Modules\User\Rules\Username;

class AdminRequest extends Request
{
    /**
     * Validate administrator identity and password confirmation.
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'username' => ['required', 'string', 'max:255', new Username, 'regex:/^[a-zA-Z0-9_.\-]+$/'],
            'email' => ['required', 'email:rfc', 'max:50'],
            'password' => [
                'required',
                'string',
                Password::min(8)
                    ->max(20)
                    ->mixedCase()
                    ->numbers()
                    ->symbols(),
                'confirmed',
            ],
        ];
    }

    /** {@inheritDoc} */
    protected function availableAttributes(): string
    {
        return 'installer::validation.attributes';
    }
}
