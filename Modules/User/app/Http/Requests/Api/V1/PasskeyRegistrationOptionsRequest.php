<?php

namespace Modules\User\Http\Requests\Api\V1;

use Modules\Core\Http\Requests\Request;

class PasskeyRegistrationOptionsRequest extends Request
{
    public function rules(): array
    {
        return [
            'current_password' => ['required', 'string'],
            'code' => ['nullable', 'string', 'max:32'],
        ];
    }

    /** {@inheritDoc} */
    protected function availableAttributes(): string
    {
        return 'user::attributes.two_factor';
    }
}
