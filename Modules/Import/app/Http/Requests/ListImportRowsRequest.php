<?php

namespace Modules\Import\Http\Requests;

use Modules\Core\Http\Requests\Request;

class ListImportRowsRequest extends Request
{
    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        return [
            'per_page' => 'sometimes|integer|min:1|max:100',
        ];
    }

    /** {@inheritDoc} */
    protected function availableAttributes(): string
    {
        return 'import::attributes.imports';
    }
}
