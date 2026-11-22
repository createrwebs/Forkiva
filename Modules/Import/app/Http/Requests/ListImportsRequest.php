<?php

namespace Modules\Import\Http\Requests;

use Modules\Core\Http\Requests\Request;

class ListImportsRequest extends Request
{
    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        return [
            'resource' => 'required|string',
            'menu_id' => 'sometimes|integer|min:1',
        ];
    }

    /** {@inheritDoc} */
    protected function availableAttributes(): string
    {
        return 'import::attributes.imports';
    }
}
