<?php

namespace Modules\Import\Http\Requests;

use Modules\Core\Http\Requests\Request;

class UploadImportRequest extends Request
{
    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        return [
            'file' => 'required|file|mimes:csv,txt,xlsx,xls|max:51200',
            'context' => 'sometimes|array:menu_id',
            'context.menu_id' => in_array($this->route('resource'), ['categories', 'products'], true)
                ? 'required|integer|min:1'
                : 'prohibited',
        ];
    }

    /** {@inheritDoc} */
    protected function availableAttributes(): string
    {
        return 'import::attributes.imports';
    }
}
