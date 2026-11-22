<?php

namespace Modules\Pos\Http\Requests\Api\V1;

use Modules\Core\Http\Requests\Request;

class CustomerDisplaySnapshotRequest extends Request
{
    public function rules(): array
    {
        return ['fingerprint' => ['nullable', 'string', 'regex:/^[a-f0-9]{64}$/']];
    }

    /** {@inheritDoc} */
    protected function availableAttributes(): string
    {
        return 'pos::attributes.customer_display';
    }
}
