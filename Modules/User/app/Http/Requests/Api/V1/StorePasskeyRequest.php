<?php

namespace Modules\User\Http\Requests\Api\V1;

class StorePasskeyRequest extends PasskeyRequest
{
    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            ...$this->credentialRules(),
        ];
    }
}
