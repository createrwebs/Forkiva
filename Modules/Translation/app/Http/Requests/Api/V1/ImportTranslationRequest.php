<?php

namespace Modules\Translation\Http\Requests\Api\V1;

use Illuminate\Validation\Rule;
use Modules\Core\Http\Requests\Request;
use Modules\Translation\Enums\TranslationImportMode;

class ImportTranslationRequest extends Request
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            '_locale' => ['nullable', Rule::in(supportedLocaleKeys())],
            'file' => ['required', 'file', 'max:5120'],
            'mode' => ['sometimes', Rule::enum(TranslationImportMode::class)],
        ];
    }

    /**
     * Resolve the requested import mode.
     */
    public function mode(): TranslationImportMode
    {
        return TranslationImportMode::tryFrom((string) $this->validated('mode'))
            ?? TranslationImportMode::FillMissing;
    }

    /** {@inheritDoc} */
    protected function availableAttributes(): string
    {
        return 'translation::attributes.translations';
    }
}
