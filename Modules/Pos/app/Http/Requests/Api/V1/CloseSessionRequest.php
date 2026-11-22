<?php

namespace Modules\Pos\Http\Requests\Api\V1;

use Modules\Core\Http\Requests\Request;

class CloseSessionRequest extends Request
{
    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        return [
            'declared_cash' => 'required|numeric|min:0|max:99999999999999',
            'review_token' => ['sometimes', 'required', 'string', 'regex:/\A[a-f0-9]{64}\z/'],
            'closing_reason' => ['sometimes', 'nullable', 'string', 'min:3', 'max:1000'],
            'variance_reason' => ['sometimes', 'nullable', 'string', 'min:3', 'max:1000'],
        ];
    }

    /** {@inheritDoc} */
    protected function availableAttributes(): string
    {
        return 'pos::attributes.pos_sessions';
    }
}
