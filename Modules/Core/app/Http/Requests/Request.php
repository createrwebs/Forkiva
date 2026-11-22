<?php

namespace Modules\Core\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

abstract class Request extends FormRequest
{
    /**
     * Set translation Attributes
     */
    protected array $translationAttributes = [];

    /**
     * Set translation attributes names
     */
    protected array $translationAttributeNames = [];

    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /** {@inheritDoc} */
    public function attributes(): array
    {
        $availableAttributes = __($this->availableAttributes());

        return [
            ...(is_array($availableAttributes) ? $availableAttributes : []),
            ...$this->translationAttributes,
        ];
    }

    /**
     * Get available attributes
     */
    abstract protected function availableAttributes(): string;

    /**
     * Get branch rule
     */
    public function getBranchRule(bool $isNullable = false, bool $force = false): array
    {
        if (! $force && $this->method() === 'PUT') {
            return [];
        }

        return [
            'branch_id' => [
                'bail',
                $isNullable ? 'nullable' : 'required',
                'numeric',
                'exists:branches,id,deleted_at,NULL',
            ],
        ];
    }

    /**
     * Get menu rule
     */
    public function getMenuRule(): array
    {
        if ($this->method() === 'PUT') {
            return [];
        }

        $user = auth()->user();

        return [
            'menu_id' => [
                'bail',
                'required',
                'numeric',
                'exists:menus,id,deleted_at,NULL'.($user->assignedToBranch() ? ",branch_id,$user->branch_id" : ''),
            ],
        ];
    }

    /**
     * Handle a passed validation attempt.
     */
    protected function passedValidation(): void
    {
        if ($this->hasHeader('Fill-Supported-Locales')) {
            $defaultLocale = setting('default_locale');
            $supportedLocales = array_filter(supportedLocaleKeys(), fn ($item) => $item !== $defaultLocale);

            foreach ($this->translationAttributeNames as $attributeName) {
                $attributeData = $this->input($attributeName);
                if (! is_array($attributeData)) {
                    continue;
                }

                $defaultValue = $attributeData[$defaultLocale] ?? null;
                if (empty($defaultValue)) {
                    continue;
                }

                $localeValues = [$defaultLocale => $defaultValue];
                foreach ($supportedLocales as $locale) {
                    $localeValues[$locale] = $attributeData[$locale] ?? $defaultValue;
                }

                $this->merge([
                    $attributeName => $localeValues,
                ]);
            }
        }
    }

    /**
     * Get translation rules
     */
    protected function getTranslationRules(array $columns): array
    {
        $validation = [];
        $transKey = $this->availableAttributes();
        $trans = __($transKey);

        foreach ($columns as $column => $rules) {
            $this->translationAttributeNames[] = $column;

            if (! is_array($rules)) {
                $rules = explode('|', $rules);
            }
            $isNullable = false;
            if (in_array('nullable', $rules)) {
                $isNullable = true;
            }
            // Set the base rule for the column as required or nullable and ensure it's an array
            $requiredRule = $rules[0] ?? null;
            if ($requiredRule === 'bail') {
                $requiredRule = $rules[1] ?? null;
            }
            $isRequiredIf = in_array(explode(':', $requiredRule)[0] ?? null, ['required_with', 'required_if']);
            $isRequired = ! is_null($requiredRule) && ($requiredRule === 'required' || $isRequiredIf);
            $validation[$column] = [($isRequired ? $requiredRule : 'nullable'), $isNullable ? 'nullable' : 'array'];

            $supportedLocaleKeys = supportedLocaleKeys();
            foreach ($supportedLocaleKeys as $locale) {
                $this->translationAttributes = [
                    ...$this->translationAttributes,
                    "$column.$locale" => ($trans[$column] ?? __("$transKey.$column")).' '.__("support::languages.$locale"),
                ];

                $localeRules = $rules;

                // If the default locale, it must be required
                if ($isRequired) {
                    if ($locale !== setting('default_locale')) {
                        if (isset($localeRules[1]) && $localeRules[1] === 'nullable') {
                            unset($localeRules[0]);
                        } else {
                            // For non-default locales, set nullable but enforce non-empty strings
                            $localeRules[0] = 'nullable';
                        }
                    }
                }

                // Always ensure non-empty strings
                $localeRules[] = 'string';
                if (count($supportedLocaleKeys) > 1 && $isRequired && ! $isRequiredIf) {
                    $localeRules[] = 'required_without_all:'.implode(',', array_map(fn ($loc) => "$column.$loc", array_diff(supportedLocaleKeys(), [$locale])));
                }

                // Apply the rules to the locale-specific attribute
                $validation["$column.$locale"] = $localeRules;
            }
        }

        return $validation;
    }
}
