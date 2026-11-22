<?php

namespace Modules\Product\Support;

use Illuminate\Support\Collection;
use Illuminate\Validation\Rule;
use Modules\Option\Enums\OptionType;
use Modules\Option\Models\Option;

class ProductOptionValidation
{
    /**
     * Get validation rules for product option input.
     *
     * @param  Collection<int, Option>  $options
     * @return array<string, array<int, mixed>>
     */
    public static function rules(Collection $options, string $root = 'options'): array
    {
        return $options
            ->flatMap(fn (Option $option) => self::optionRules($option, "$root.$option->id"))
            ->all();
    }

    /**
     * Normalize option data before validation.
     *
     * @param  array<string, mixed>|null  $options
     * @return array<string, mixed>
     */
    public static function normalize(?array $options): array
    {
        return array_filter($options ?? [], fn (mixed $value) => match (true) {
            is_array($value) => count($value) > 0,
            default => filled($value),
        });
    }

    /**
     * Get shared validation messages for option fields.
     *
     * @return array<string, string>
     */
    public static function messages(string $root = 'options'): array
    {
        return [
            "$root.*.required" => __('cart::validation.this_field_is_required'),
            "$root.*.in" => __('cart::validation.the_selected_option_is_invalid'),
            "$root.*.*.in" => __('cart::validation.the_selected_option_is_invalid'),
        ];
    }

    /**
     * Get rules for one product option.
     *
     * @return array<string, array<int, mixed>>
     */
    private static function optionRules(Option $option, string $key): array
    {
        $rules = [$option->is_required ? 'required' : 'nullable'];
        $type = $option->type instanceof OptionType
            ? $option->type
            : OptionType::tryFrom((string) $option->type);

        if ($type?->isFieldType()) {
            return [$key => [...$rules, 'string', 'max:255']];
        }

        $valueIds = $option->values->pluck('id')->all();

        if (in_array($type, [OptionType::Radio, OptionType::Select], true)) {
            return [$key => [...$rules, 'integer', Rule::in($valueIds)]];
        }

        return [
            $key => [...$rules, 'array'],
            "$key.*" => ['integer', 'distinct', Rule::in($valueIds)],
        ];
    }
}
