<?php

namespace Modules\Export\Http\Requests\Api\V1;

use Illuminate\Validation\Rule;
use Modules\Core\Http\Requests\Request;
use Modules\Export\Enums\ExportFormat;

class StoreExportRequest extends Request
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string,mixed>
     */
    public function rules(): array
    {
        return [
            'format' => ['required', 'string', Rule::in(ExportFormat::values())],
            'filters' => ['nullable', 'array'],
            'sorts' => ['nullable', 'array'],
            'context' => ['nullable', 'array'],
            'context_key' => ['required', 'string', 'max:255'],
        ];
    }

    /** {@inheritDoc} */
    protected function availableAttributes(): string
    {
        return 'export::attributes.exports';
    }
}
