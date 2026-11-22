<?php

namespace Modules\User\Http\Requests\Api\V1;

use Illuminate\Validation\Rule;
use Modules\Core\Http\Requests\Request;
use Modules\User\Enums\TwoFactorMethod;

class TwoFactorSetupRequest extends Request
{
    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        return [
            'method' => ['required', Rule::enum(TwoFactorMethod::class)],
            'current_password' => 'required|string',
            'code' => 'nullable|string|max:32',
        ];
    }

    /** {@inheritDoc} */
    protected function availableAttributes(): string
    {
        return 'user::attributes.two_factor';
    }
}
