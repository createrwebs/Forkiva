<?php

namespace Modules\Import\Http\Requests;

use Illuminate\Validation\Rule;
use Modules\Core\Http\Requests\Request;
use Modules\Import\Enums\ImportMode;

class ValidateImportRequest extends Request
{
    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        return [
            'mode' => ['required', Rule::enum(ImportMode::class)],
        ];
    }

    /** {@inheritDoc} */
    protected function availableAttributes(): string
    {
        return 'import::attributes.imports';
    }
}
