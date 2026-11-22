<?php

namespace Modules\Translation\Http\Requests\Api\V1;

use Illuminate\Validation\Rule;
use Modules\Core\Http\Requests\Request;
use Modules\Translation\Enums\TranslationIndexStatus;

class IndexTranslationRequest extends Request
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'page' => ['nullable', 'integer', 'min:1'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
            'search' => ['nullable', 'string', 'max:255'],
            'locale' => ['nullable', Rule::in(supportedLocaleKeys())],
            'status' => ['nullable', Rule::enum(TranslationIndexStatus::class)],
            'prefix' => ['nullable', 'string', 'max:255'],
        ];
    }

    /**
     * Resolve the requested table status filter.
     */
    public function status(): TranslationIndexStatus
    {
        return TranslationIndexStatus::tryFrom((string) $this->validated('status'))
            ?? TranslationIndexStatus::All;
    }

    /** {@inheritDoc} */
    protected function availableAttributes(): string
    {
        return 'translation::attributes.translations';
    }
}
