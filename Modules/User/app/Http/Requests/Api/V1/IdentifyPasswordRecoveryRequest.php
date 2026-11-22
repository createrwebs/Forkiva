<?php

namespace Modules\User\Http\Requests\Api\V1;

use Modules\Core\Http\Requests\Request;

class IdentifyPasswordRecoveryRequest extends Request
{
    /**
     * Get the validation rules for a username or email address.
     */
    public function rules(): array
    {
        return ['identifier' => ['required', 'string', 'max:254']];
    }

    /**
     * Trim surrounding whitespace before resolving the account identifier.
     */
    protected function prepareForValidation(): void
    {
        if (is_string($this->input('identifier'))) {
            $this->merge(['identifier' => trim($this->input('identifier'))]);
        }
    }

    /** {@inheritDoc} */
    protected function availableAttributes(): string
    {
        return 'user::attributes.password_recovery';
    }
}
