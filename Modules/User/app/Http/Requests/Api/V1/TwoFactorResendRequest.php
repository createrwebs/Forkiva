<?php

namespace Modules\User\Http\Requests\Api\V1;

use Modules\Core\Http\Requests\Request;

class TwoFactorResendRequest extends Request
{
    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        return ['challenge_token' => 'required|string|size:64'];
    }

    /** {@inheritDoc} */
    protected function availableAttributes(): string
    {
        return 'user::attributes.two_factor';
    }
}
