<?php

namespace Modules\Service\Services\ServiceRegistry;

use Illuminate\Support\Arr;
use Illuminate\Validation\Rule;
use Modules\Setting\Models\Setting;
use Modules\Setting\Repositories\SettingRepository;
use Nwidart\Modules\Facades\Module;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use ValueError;

class ServiceRegistryService implements ServiceRegistryServiceInterface
{
    /** {@inheritDoc} */
    public function all(): array
    {
        return array_values(array_map(
            fn (array $category) => $this->formatCategory($category),
            $this->rawCategories()
        ));
    }

    /**
     * @param  array<string, mixed>  $category
     * @return array<string, mixed>
     */
    protected function formatCategory(array $category): array
    {
        return [
            'key' => $category['key'],
            'label' => __($category['label']),
            'description' => $category['description'] ? __($category['description']) : null,
            'icon' => $category['icon'],
            'order' => $category['order'],
            'services' => array_values(array_map(
                fn (array $service) => $this->formatService($category['key'], $service),
                $category['services']
            )),
        ];
    }

    /**
     * @param  array<string, mixed>  $service
     * @return array<string, mixed>
     */
    protected function formatService(string $category, array $service): array
    {
        $values = $this->serviceValues($category, $service);

        return [
            'key' => $service['slug'],
            'original_key' => $service['key'],
            'category' => $category,
            'name' => isset($service['name']) ? __($service['name']) : $service['slug'],
            'description' => isset($service['description']) ? __($service['description']) : null,
            'icon' => $service['icon'] ?? null,
            'color' => $service['color'] ?? null,
            'order' => (int) ($service['order'] ?? 0),
            'values' => $values,
            'settings' => $this->formatFields($service, $values),
        ];
    }

    /**
     * @param  array<string, mixed>  $service
     * @return array<string, mixed>
     */
    protected function serviceValues(string $category, array $service): array
    {
        $baseKey = $this->settingsBaseKey($category, $service);
        $values = [];

        foreach (($service['settings'] ?? []) as $fieldKey => $field) {
            $values[$fieldKey] = setting("$baseKey.$fieldKey", $field['default'] ?? null);

            foreach (($field['conditional_fields'] ?? []) as $optionKey => $conditionalFields) {
                foreach ($conditionalFields as $conditionalKey => $conditionalField) {
                    $values[$optionKey][$conditionalKey] = setting(
                        "$baseKey.$optionKey.$conditionalKey",
                        $conditionalField['default'] ?? null
                    );
                }
            }
        }

        return $values;
    }

    /**
     * Get settings base key
     */
    protected function settingsBaseKey(string $category, array $service): string
    {
        return "services.$category.{$service['slug']}";
    }

    /**
     * Format fields
     *
     * @param  array<string, mixed>  $service
     * @param  array<string, mixed>  $values
     * @return array<int, array<string, mixed>>
     */
    protected function formatFields(array $service, array $values): array
    {
        $fields = [];
        $module = (string) ($service['_module'] ?? 'service');

        foreach (($service['settings'] ?? []) as $fieldKey => $field) {
            $formatted = $this->formatField($module, $fieldKey, $field, $values[$fieldKey] ?? null);
            $conditionalFields = [];

            foreach (($field['conditional_fields'] ?? []) as $optionKey => $optionFields) {
                foreach ($optionFields as $conditionalKey => $conditionalField) {
                    $conditionalFields[$optionKey][] = $this->formatField(
                        $module,
                        $conditionalKey,
                        $conditionalField,
                        Arr::get($values, "$optionKey.$conditionalKey"),
                        "$optionKey.$conditionalKey"
                    );
                }
            }

            if ($conditionalFields !== []) {
                $formatted['conditional_fields'] = $conditionalFields;
            }

            $fields[] = $formatted;
        }

        return $fields;
    }

    /**
     * @param  array<string, mixed>  $field
     * @return array<string, mixed>
     */
    protected function formatField(
        string $module,
        string $fieldKey,
        array $field,
        mixed $value,
        ?string $attributePath = null
    ): array {
        $attributePath ??= $fieldKey;
        $options = $field['options'] ?? null;

        return array_filter([
            'key' => $fieldKey,
            'label' => $this->fieldLabel($module, $attributePath, $field),
            'type' => $field['type'] ?? 'string',
            'default' => $field['default'] ?? null,
            'value' => $value,
            'validation' => $field['validation'] ?? null,
            'options' => $this->fieldOptions($options),
            'value_option' => $this->fieldValueOption($options, $value),
            'is_sensitive' => $this->isSensitiveField($field),
        ], fn (mixed $value) => ! is_null($value));
    }

    /**
     * Get field label
     */
    protected function fieldLabel(string $module, string $attributePath, array $field): string
    {
        if (isset($field['label'])) {
            return __((string) $field['label']);
        }

        return __("$module::attributes.settings.$attributePath");
    }

    /**
     * Get field options
     *
     * @return array<int, array<string, mixed>>|null
     */
    protected function fieldOptions(mixed $options): ?array
    {
        if (is_string($options) && class_exists($options)) {
            if (method_exists($options, 'toArrayTrans')) {
                return $options::toArrayTrans();
            }

            if (method_exists($options, 'values')) {
                return array_map(
                    fn (string $value) => ['id' => $value, 'name' => $value],
                    $options::values()
                );
            }
        }

        if (is_array($options)) {
            return array_is_list($options)
                ? $options
                : array_map(
                    fn (string|int $key, mixed $value) => ['id' => $key, 'name' => $value],
                    array_keys($options),
                    $options
                );
        }

        return null;
    }

    /**
     * Field value option
     *
     * @return array<string, mixed>|null
     */
    protected function fieldValueOption(mixed $options, mixed $value): ?array
    {
        if (is_null($value) || $value === '') {
            return null;
        }

        if (is_string($options) && class_exists($options) && method_exists($options, 'from')) {
            try {
                $case = $options::from($value);

                if (method_exists($case, 'toTrans')) {
                    return $case->toTrans();
                }
            } catch (ValueError) {
                return null;
            }
        }

        return collect($this->fieldOptions($options) ?? [])
            ->firstWhere('id', $value);
    }

    /**
     * Determine is sensitive field
     *
     * @param  array<string, mixed>  $field
     */
    protected function isSensitiveField(array $field): bool
    {
        return (bool) ($field['sensitive'] ?? $field['is_sensitive'] ?? false);
    }

    /**
     * Raw categories
     *
     * @return array<int, array<string, mixed>>
     */
    protected function rawCategories(): array
    {
        $categories = [];

        foreach (Module::getOrdered() as $module) {
            $file = $module->getPath().DIRECTORY_SEPARATOR.'config/services.php';
            if (! file_exists($file)) {
                continue;
            }

            $moduleCategories = require $file;
            if (! is_array($moduleCategories)) {
                continue;
            }

            foreach ($moduleCategories as $category) {
                if (! is_array($category) || empty($category['key'])) {
                    continue;
                }

                $categoryKey = (string) $category['key'];
                if (! isset($categories[$categoryKey])) {
                    $categories[$categoryKey] = [
                        'key' => $categoryKey,
                        '_module' => $module->getLowerName(),
                        'label' => $category['label'] ?? $categoryKey,
                        'description' => $category['description'] ?? null,
                        'icon' => $category['icon'] ?? null,
                        'order' => (int) ($category['order'] ?? 0),
                        'services' => [],
                    ];
                }

                foreach (($category['services'] ?? []) as $service) {
                    if (! is_array($service) || empty($service['key'])) {
                        continue;
                    }

                    $slug = $this->serviceSlug($categoryKey, (string) $service['key']);
                    $categories[$categoryKey]['services'][$slug] = [
                        ...$service,
                        'key' => (string) $service['key'],
                        'slug' => $slug,
                        '_module' => $module->getLowerName(),
                    ];
                }
            }
        }

        foreach ($categories as &$category) {
            uasort($category['services'], function (array $first, array $second) {
                return [(int) ($first['order'] ?? 0), $first['slug']]
                    <=> [(int) ($second['order'] ?? 0), $second['slug']];
            });
        }

        uasort($categories, function (array $first, array $second) {
            return [(int) ($first['order'] ?? 0), $first['key']]
                <=> [(int) ($second['order'] ?? 0), $second['key']];
        });

        return $categories;
    }

    /**
     * Get service slug
     */
    protected function serviceSlug(string $category, string $key): string
    {
        $prefix = "$category.";

        return str_starts_with($key, $prefix)
            ? substr($key, strlen($prefix))
            : $key;
    }

    /** {@inheritDoc} */
    public function rules(string $category, string $service, array $data): array
    {
        [, $servicePayload] = $this->findRawService($category, $service);
        $rules = [];
        $enabled = $this->settingsEnabled($servicePayload, $data);

        foreach (($servicePayload['settings'] ?? []) as $fieldKey => $field) {
            if (! $enabled && $fieldKey !== 'enabled') {
                continue;
            }

            $fieldRules = $this->fieldRules($field);
            if ($fieldRules !== []) {
                $rules[$fieldKey] = $fieldRules;
            }

            $selectedOption = (string) ($data[$fieldKey] ?? '');
            $conditionalFields = $field['conditional_fields'] ?? [];
            if ($selectedOption === '' || ! isset($conditionalFields[$selectedOption])) {
                continue;
            }

            $rules[$selectedOption] = ['required', 'array'];
            foreach ($conditionalFields[$selectedOption] as $conditionalKey => $conditionalField) {
                $conditionalRules = $this->fieldRules($conditionalField);
                if ($conditionalRules !== []) {
                    $rules["$selectedOption.$conditionalKey"] = $conditionalRules;
                }
            }
        }

        return $rules;
    }

    /**
     * Find raw service
     *
     * @return array{0: array<string, mixed>, 1: array<string, mixed>}
     */
    protected function findRawService(string $category, string $service): array
    {
        $categories = $this->rawCategories();
        $categoryPayload = $categories[$category] ?? null;

        if (! $categoryPayload || ! isset($categoryPayload['services'][$service])) {
            throw new NotFoundHttpException(__('admin::messages.resource_not_found', [
                'resource' => __('service::services.service'),
            ]));
        }

        return [$categoryPayload, $categoryPayload['services'][$service]];
    }

    /**
     * Settings enabled
     *
     * @param  array<string, mixed>  $service
     * @param  array<string, mixed>  $data
     */
    protected function settingsEnabled(array $service, array $data): bool
    {
        if (! isset($service['settings']['enabled'])) {
            return true;
        }

        return filter_var($data['enabled'] ?? false, FILTER_VALIDATE_BOOLEAN);
    }

    /**
     * Field rules
     *
     * @param  array<string, mixed>  $field
     * @return array<int, mixed>
     */
    protected function fieldRules(array $field): array
    {
        $validation = $field['validation'] ?? null;
        if (! $validation) {
            return [];
        }

        $rules = is_array($validation) ? $validation : explode('|', (string) $validation);
        $optionValues = $this->fieldOptionValues($field['options'] ?? null);

        return array_map(function (mixed $rule) use ($optionValues) {
            if ($rule === 'in:options') {
                return Rule::in($optionValues);
            }

            return $rule;
        }, $rules);
    }

    /**
     * Field option values
     *
     * @return array<int, mixed>
     */
    protected function fieldOptionValues(mixed $options): array
    {
        if (is_string($options) && class_exists($options) && method_exists($options, 'values')) {
            return $options::values();
        }

        if (is_array($options)) {
            return array_is_list($options) ? $options : array_keys($options);
        }

        return [];
    }

    /** {@inheritDoc} */
    public function attributes(string $category, string $service): array
    {
        [, $servicePayload] = $this->findRawService($category, $service);
        $attributes = [];
        $module = (string) ($servicePayload['_module'] ?? 'service');

        foreach (($servicePayload['settings'] ?? []) as $fieldKey => $field) {
            $attributes[$fieldKey] = $this->fieldLabel($module, $fieldKey, $field);

            foreach (($field['conditional_fields'] ?? []) as $optionKey => $conditionalFields) {
                foreach ($conditionalFields as $conditionalKey => $conditionalField) {
                    $attributes["$optionKey.$conditionalKey"] = $this->fieldLabel(
                        $module,
                        "$optionKey.$conditionalKey",
                        $conditionalField
                    );
                }
            }
        }

        return $attributes;
    }

    /** {@inheritDoc} */
    public function update(string $category, string $service, array $data): array
    {
        [$categoryPayload, $servicePayload] = $this->findRawService($category, $service);
        $baseKey = $this->settingsBaseKey($categoryPayload['key'], $servicePayload);
        $settings = [];
        $encryptable = [];
        $enabled = $this->settingsEnabled($servicePayload, $data);

        foreach (($servicePayload['settings'] ?? []) as $fieldKey => $field) {
            if (! $enabled && $fieldKey !== 'enabled') {
                continue;
            }

            $value = array_key_exists($fieldKey, $data)
                ? $data[$fieldKey]
                : ($field['default'] ?? null);

            $this->putSettingValue($settings, $encryptable, "$baseKey.$fieldKey", $field, $value);

            $selectedOption = (string) ($data[$fieldKey] ?? '');
            $conditionalFields = $field['conditional_fields'] ?? [];
            if ($selectedOption === '' || ! isset($conditionalFields[$selectedOption])) {
                continue;
            }

            $selectedPayload = Arr::get($data, $selectedOption, []);
            foreach ($conditionalFields[$selectedOption] as $conditionalKey => $conditionalField) {
                $conditionalValue = is_array($selectedPayload) && array_key_exists($conditionalKey, $selectedPayload)
                    ? $selectedPayload[$conditionalKey]
                    : ($conditionalField['default'] ?? null);

                $this->putSettingValue(
                    $settings,
                    $encryptable,
                    "$baseKey.$selectedOption.$conditionalKey",
                    $conditionalField,
                    $conditionalValue
                );
            }
        }

        if ($settings !== []) {
            Setting::setMany($settings);
        }

        if ($encryptable !== []) {
            Setting::setEncryptableSettings($encryptable);
        }

        $this->refreshSettingBinding();

        return $this->show($category, $service);
    }

    /**
     * Put setting value
     */
    protected function putSettingValue(
        array &$settings,
        array &$encryptable,
        string $settingKey,
        array $field,
        mixed $value
    ): void {
        if ($this->isSensitiveField($field)) {
            $encryptable[$settingKey] = $value;

            return;
        }

        $settings[$settingKey] = $value;
    }

    /**
     * Refresh setting binding
     */
    protected function refreshSettingBinding(): void
    {
        app()->forgetInstance('setting');
        app()->singleton('setting', fn () => new SettingRepository(Setting::allCached()));
    }

    /** {@inheritDoc} */
    public function show(string $category, string $service): array
    {
        [$categoryPayload, $servicePayload] = $this->findRawService($category, $service);

        return $this->formatService($categoryPayload['key'], $servicePayload);
    }
}
