<?php

namespace Modules\Translation\Http\Requests\Api\V1;

use Illuminate\Validation\Rule;
use Modules\Core\Http\Requests\Request;
use Modules\Translation\Enums\TranslationExportMode;

class ExportTranslationRequest extends Request
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            '_locale' => ['required', Rule::in(supportedLocaleKeys())],
            'mode' => ['nullable', Rule::enum(TranslationExportMode::class)],
        ];
    }

    /**
     * Resolve the requested export mode.
     */
    public function mode(): TranslationExportMode
    {
        return TranslationExportMode::tryFrom((string) $this->validated('mode'))
            ?? TranslationExportMode::Values;
    }

    /** {@inheritDoc} */
    protected function availableAttributes(): string
    {
        return 'translation::attributes.translations';
    }
}
