<?php

namespace Modules\Import\Http\Requests;

use Modules\Core\Http\Requests\Request;
use Modules\Import\Services\Import\ImportServiceInterface;

class EditImportRowRequest extends Request
{
    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        $import = app(ImportServiceInterface::class)->find((int) $this->route('id'));
        $allowed = array_keys($import->mapping);

        return [
            'data' => 'required|array:'.implode(',', $allowed),
            'data.*' => 'nullable|string|max:10000',
        ];
    }

    /** {@inheritDoc} */
    protected function availableAttributes(): string
    {
        return 'import::attributes.imports';
    }
}
