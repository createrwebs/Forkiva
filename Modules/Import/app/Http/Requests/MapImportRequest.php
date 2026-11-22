<?php

namespace Modules\Import\Http\Requests;

use Modules\Core\Http\Requests\Request;
use Modules\Import\Services\Import\ImportServiceInterface;

class MapImportRequest extends Request
{
    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        $service = app(ImportServiceInterface::class);
        $import = $service->find((int) $this->route('id'));
        $allowed = array_column($service->fields($import->resource_key), 'key');

        return [
            'mapping' => 'required|array:'.implode(',', $allowed),
            'mapping.*' => 'required|integer|min:0',
        ];
    }

    /** {@inheritDoc} */
    protected function availableAttributes(): string
    {
        return 'import::attributes.imports';
    }
}
