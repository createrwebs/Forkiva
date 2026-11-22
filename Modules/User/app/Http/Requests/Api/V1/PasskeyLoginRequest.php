<?php

namespace Modules\User\Http\Requests\Api\V1;

class PasskeyLoginRequest extends PasskeyRequest
{
    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        return [
            ...$this->credentialRules(),
            'remember' => ['sometimes', 'boolean'],
        ];
    }
}
