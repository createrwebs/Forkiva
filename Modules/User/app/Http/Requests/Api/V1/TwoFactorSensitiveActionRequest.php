<?php

namespace Modules\User\Http\Requests\Api\V1;

use Modules\Core\Http\Requests\Request;

class TwoFactorSensitiveActionRequest extends Request
{
    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        return [
            'current_password' => 'required|string',
            'code' => 'required|string|max:32',
        ];
    }

    /** {@inheritDoc} */
    protected function availableAttributes(): string
    {
        return 'user::attributes.two_factor';
    }
}
