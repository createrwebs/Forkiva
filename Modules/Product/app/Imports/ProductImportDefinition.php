<?php

namespace Modules\Product\Imports;

use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Modules\Category\Models\Category;
use Modules\Import\Contracts\ContextualImportFieldsInterface;
use Modules\Import\Contracts\ImportDefinitionInterface;
use Modules\Import\Enums\ImportMode;
use Modules\Import\Enums\ImportOperation;
use Modules\Import\Models\DataImport;
use Modules\Menu\Models\Menu;
use Modules\Product\Enums\ProductInventoryConsumptionMode;
use Modules\Product\Models\Product;
use Modules\Support\Enums\PriceType;
use Modules\Tax\Models\Tax;

class ProductImportDefinition implements ContextualImportFieldsInterface, ImportDefinitionInterface
{
    /** {@inheritDoc} */
    public function fields(): array
    {
        $fields = [
            ['key' => 'id', 'label' => __('product::products.import.id'), 'type' => 'integer', 'required' => false],
            ['key' => 'sku', 'label' => __('product::products.table.sku'), 'type' => 'text', 'required' => false],
            ['key' => 'name', 'label' => __('product::products.table.name'), 'type' => 'text', 'required' => true],
            ['key' => 'description', 'label' => __('product::products.table.description'), 'type' => 'text', 'required' => false],
            ['key' => 'price', 'label' => __('product::products.table.price'), 'type' => 'text', 'required' => true],
            ['key' => 'special_price', 'label' => __('product::attributes.products.special_price'), 'type' => 'text', 'required' => false],
            [
                'key' => 'special_price_type',
                'label' => __('product::attributes.products.special_price_type'),
                'type' => 'text',
                'required' => false,
                'options' => $this->enumOptions(PriceType::toArrayTrans()),
            ],
            ['key' => 'special_price_start', 'label' => __('product::attributes.products.special_price_start'), 'type' => 'date', 'required' => false],
            ['key' => 'special_price_end', 'label' => __('product::attributes.products.special_price_end'), 'type' => 'date', 'required' => false],
            ['key' => 'prep_time_minutes', 'label' => __('product::products.import.prep_time_minutes'), 'type' => 'integer', 'required' => false],
            ['key' => 'new_from', 'label' => __('product::attributes.products.new_from'), 'type' => 'date', 'required' => false],
            ['key' => 'new_to', 'label' => __('product::attributes.products.new_to'), 'type' => 'date', 'required' => false],
            ['key' => 'category_slugs', 'label' => __('product::products.import.category_slugs'), 'type' => 'text', 'required' => false, 'multiple' => true],
            ['key' => 'tax_ids', 'label' => __('product::products.import.tax_ids'), 'type' => 'text', 'required' => false, 'multiple' => true],
            ['key' => 'is_active', 'label' => __('admin::admin.table.activation'), 'type' => 'boolean', 'required' => true],
        ];

        foreach ($this->otherLocales() as $locale) {
            $fields[] = ['key' => "name_$locale", 'label' => __('product::products.table.name')." ($locale)", 'type' => 'text', 'required' => false];
            $fields[] = ['key' => "description_$locale", 'label' => __('product::products.table.description')." ($locale)", 'type' => 'text', 'required' => false];
        }

        return $fields;
    }

    /** {@inheritDoc} */
    public function fieldsForContext(array $context): array
    {
        $menuId = $this->resolveContext($context)['menu_id'];
        $menu = Menu::query()->withoutGlobalActive()->findOrFail($menuId);
        $choices = [
            'category_slugs' => array_map(
                fn (array $category) => ['value' => $category['slug'], 'label' => $category['name']],
                Category::treeListWithSlug($menuId)
            ),
            'tax_ids' => array_map(
                fn (array $tax) => ['value' => (string) $tax['id'], 'label' => $tax['name'].' (#'.$tax['id'].')'],
                Tax::list($menu->branch_id, false)->all()
            ),
        ];

        return array_map(function (array $field) use ($choices): array {
            if (isset($choices[$field['key']])) {
                $field['options'] = $choices[$field['key']];
            }

            return $field;
        }, $this->fields());
    }

    /** {@inheritDoc} */
    public function authorize(?DataImport $import = null): void
    {
        abort_unless(auth()->user()?->can('admin.products.import'), 403);

        if ($import) {
            Menu::query()
                ->withoutGlobalActive()
                ->findOrFail((int) ($import->context['menu_id'] ?? 0));
        }
    }

    /** {@inheritDoc} */
    public function resolveContext(array $input): array
    {
        $menuId = (int) ($input['menu_id'] ?? 0);
        abort_unless($menuId > 0, 422, __('product::products.import.menu_required'));

        $context = ['menu_id' => $menuId];
        $this->authorize(new DataImport(['context' => $context]));

        return $context;
    }

    /** {@inheritDoc} */
    public function key(array $data, DataImport $import): ?string
    {
        $id = trim((string) ($data['id'] ?? ''));
        if ($id !== '' && $import->mode !== ImportMode::CreateOnly) {
            return 'id:'.hash('sha256', $id);
        }

        $sku = trim((string) ($data['sku'] ?? ''));
        if ($sku !== '') {
            return 'sku:'.hash('sha256', $sku);
        }

        $name = mb_strtolower(trim((string) ($data['name'] ?? '')));

        return $name === '' ? null : 'name:'.hash('sha256', $name);
    }

    /** {@inheritDoc} */
    public function validate(array $data, DataImport $import): array
    {
        $data = $this->normalize($data);
        if ($import->mode === ImportMode::CreateOnly) {
            $data['id'] = null;
        }

        $rules = [
            'id' => 'nullable|integer|min:1',
            'sku' => 'nullable|string|max:255',
            'name' => 'required|string|max:255',
            'description' => 'nullable|string|max:1000',
            'price' => 'required|numeric|min:0|max:99999999999999|decimal:0,4',
            'special_price' => 'nullable|numeric|min:0|max:99999999999999|decimal:0,4',
            'special_price_type' => ['nullable', Rule::enum(PriceType::class)],
            'special_price_start' => 'nullable|date',
            'special_price_end' => 'nullable|date',
            'prep_time_minutes' => 'nullable|integer|min:0|max:1440',
            'new_from' => 'nullable|date',
            'new_to' => 'nullable|date',
            'category_slugs' => 'nullable|string|max:5000',
            'tax_ids' => 'nullable|string|max:5000',
            'is_active' => 'required|boolean',
        ];
        foreach ($this->otherLocales() as $locale) {
            $rules["name_$locale"] = 'nullable|string|max:255';
            $rules["description_$locale"] = 'nullable|string|max:1000';
        }
        $errors = Validator::make($data, $rules)->errors()->toArray();
        if ($errors !== []) {
            return compact('data', 'errors') + ['operation' => ImportOperation::Invalid, 'target_id' => null];
        }
        if (array_key_exists('tax_codes', $data) ||
            (! array_key_exists('tax_ids', $data) && collect($import->headers ?? [])
                ->contains(fn ($header) => Str::snake(mb_strtolower(trim((string) $header))) === 'tax_codes'))) {
            $errors['tax_ids'] = [__('product::products.import.tax_ids_required')];
        }

        $menuId = (int) $import->context['menu_id'];
        $query = Product::query()->withoutGlobalActive()->where('menu_id', $menuId);
        $locale = setting('default_locale') ?: 'en';
        $existing = match (true) {
            $data['id'] !== null => (clone $query)->find($data['id']),
            ($data['sku'] ?? null) !== null => (clone $query)->where('sku', $data['sku'])->first(),
            default => (clone $query)->where("name->$locale", $data['name'])->first(),
        };

        if (($data['id'] ?? null) !== null && ! $existing) {
            $errors['id'] = [__('product::products.import.missing_id')];
        }
        if (($data['sku'] ?? null) !== null && (clone $query)->where('sku', $data['sku'])
            ->when($existing, fn ($query) => $query->where('id', '!=', $existing->id))->exists()) {
            $errors['sku'] = [__('product::products.import.sku_exists')];
        }

        [$operation, $modeError] = $this->operation($import->mode, (bool) $existing);
        if ($modeError) {
            $field = ($data['id'] ?? null) !== null ? 'id' : (($data['sku'] ?? null) !== null ? 'sku' : 'name');
            $errors[$field] ??= [$modeError];
        }
        if ($operation === ImportOperation::Create && ! auth()->user()->can('admin.products.create')) {
            $errors['name'] = [__('product::products.import.cannot_create')];
        }
        if ($operation === ImportOperation::Update && ! auth()->user()->can('admin.products.edit')) {
            $errors['id'] = [__('product::products.import.cannot_update')];
        }

        $menu = Menu::query()->withoutGlobalActive()->findOrFail($menuId);
        $errors = array_merge(
            $errors,
            $this->validatePricing($data, $existing, $import),
            $this->validateRelations($data, $menu),
        );

        return compact('data', 'errors', 'operation') + ['target_id' => $existing?->id];
    }

    /** {@inheritDoc} */
    public function apply(array $data, DataImport $import, ?int $targetId): void
    {
        $menuId = (int) $import->context['menu_id'];
        Menu::query()->withoutGlobalActive()->findOrFail($menuId);
        $product = $targetId === null
            ? new Product
            : Product::query()->withoutGlobalActive()->where('menu_id', $menuId)->findOrFail($targetId);

        if (! $product->exists) {
            $product->menu_id = $menuId;
            $product->inventory_consumption_mode = ProductInventoryConsumptionMode::DirectRecipe;
        }

        $locale = setting('default_locale') ?: 'en';
        $product->setTranslation('name', $locale, $data['name']);
        foreach ([
            'description',
            'sku',
            'prep_time_minutes',
            'special_price',
            'special_price_type',
            'special_price_start',
            'special_price_end',
            'new_from',
            'new_to',
        ] as $field) {
            if (array_key_exists($field, $data) &&
                ($import->mode === ImportMode::ReplaceExisting || ! $product->exists || $data[$field] !== null)) {
                if ($field === 'description') {
                    $product->setTranslation($field, $locale, $data[$field] ?? '');
                } elseif ($field === 'prep_time_minutes') {
                    $product->{$field} = $data[$field] ?? 0;
                } else {
                    $product->{$field} = $data[$field];
                }
            }
        }
        foreach ($this->otherLocales() as $otherLocale) {
            foreach (['name', 'description'] as $field) {
                $key = "{$field}_$otherLocale";
                if (array_key_exists($key, $data) &&
                    ($import->mode === ImportMode::ReplaceExisting || $data[$key] !== null)) {
                    $product->setTranslation($field, $otherLocale, $data[$key] ?? '');
                }
            }
        }
        $product->price = $data['price'];
        $product->is_active = (bool) $data['is_active'];
        $product->save();

        if (array_key_exists('category_slugs', $data) &&
            ($data['category_slugs'] !== null || $import->mode === ImportMode::ReplaceExisting)) {
            $product->categories()->sync(Category::query()
                ->where('menu_id', $menuId)
                ->whereIn('slug', $this->listValues($data['category_slugs']))
                ->pluck('id'));
        }
        if (array_key_exists('tax_ids', $data) &&
            ($data['tax_ids'] !== null || $import->mode === ImportMode::ReplaceExisting)) {
            $branchId = (int) $product->menu->branch_id;
            $product->taxes()->sync(Tax::query()
                ->withOutGlobalBranchPermission()
                ->where('is_global', false)
                ->where(fn ($query) => $query->where('branch_id', $branchId)->orWhereNull('branch_id'))
                ->whereIn('id', $this->listValues($data['tax_ids']))
                ->pluck('id'));
        }
    }

    /** {@inheritDoc} */
    public function validateDependencies(DataImport $import, int $afterId = 0): ?int
    {
        return null;
    }

    /** {@inheritDoc} */
    public function afterImport(DataImport $import, int $afterId = 0): ?int
    {
        return null;
    }

    private function validatePricing(array $data, ?Product $existing, DataImport $import): array
    {
        $specialPrice = $this->effective($data, 'special_price', $existing, $import);
        $specialType = $this->effective($data, 'special_price_type', $existing, $import);
        $errors = Validator::make([
            'price' => $data['price'],
            'special_price' => $specialPrice,
            'special_price_type' => $specialType,
            'special_price_start' => $this->effective($data, 'special_price_start', $existing, $import),
            'special_price_end' => $this->effective($data, 'special_price_end', $existing, $import),
            'new_from' => $this->effective($data, 'new_from', $existing, $import),
            'new_to' => $this->effective($data, 'new_to', $existing, $import),
        ], [
            'special_price' => 'nullable|lt:price',
            'special_price_type' => 'required_with:special_price',
            'special_price_end' => 'nullable|after:special_price_start',
            'new_to' => 'nullable|after:new_from',
        ])->errors()->toArray();

        return $errors;
    }

    private function validateRelations(array $data, Menu $menu): array
    {
        $errors = [];
        if (($data['category_slugs'] ?? null) !== null) {
            $slugs = $this->listValues($data['category_slugs']);
            $found = Category::query()
                ->where('menu_id', $menu->id)
                ->whereIn('slug', $slugs)
                ->pluck('slug')
                ->all();
            $missing = array_diff($slugs, $found);
            if ($missing !== []) {
                $errors['category_slugs'] = [__('product::products.import.unknown_categories', ['values' => implode(', ', $missing)])];
            }
        }
        if (($data['tax_ids'] ?? null) !== null) {
            $ids = $this->listValues($data['tax_ids']);
            $found = Tax::query()
                ->withOutGlobalBranchPermission()
                ->where('is_global', false)
                ->where(fn ($query) => $query->where('branch_id', $menu->branch_id)->orWhereNull('branch_id'))
                ->whereIn('id', $ids)
                ->pluck('id')
                ->map(fn ($id) => (string) $id)
                ->all();
            $missing = array_diff($ids, $found);
            if ($missing !== []) {
                $errors['tax_ids'] = [__('product::products.import.unknown_taxes', ['values' => implode(', ', $missing)])];
            }
        }

        return $errors;
    }

    private function effective(array $data, string $field, ?Product $existing, DataImport $import): mixed
    {
        if ($existing && (! array_key_exists($field, $data) ||
            ($data[$field] === null && $import->mode !== ImportMode::ReplaceExisting))) {
            return $existing->getRawOriginal($field);
        }

        return $data[$field] ?? null;
    }

    private function listValues(?string $value): array
    {
        if ($value === '[]') {
            return [];
        }

        return array_values(array_unique(array_filter(
            array_map('trim', preg_split('/[|,]/', $value ?? '') ?: []),
            fn (string $part) => $part !== ''
        )));
    }

    private function enumOptions(array $cases): array
    {
        return array_map(
            fn (array $case) => ['value' => (string) $case['id'], 'label' => $case['name']],
            $cases
        );
    }

    private function normalize(array $data): array
    {
        foreach (['id', 'name', 'price', 'is_active'] as $field) {
            $data[$field] = isset($data[$field]) ? trim((string) $data[$field]) : null;
            if ($data[$field] === '') {
                $data[$field] = null;
            }
        }
        foreach ([
            'sku',
            'description',
            'special_price',
            'special_price_type',
            'special_price_start',
            'special_price_end',
            'prep_time_minutes',
            'new_from',
            'new_to',
            'category_slugs',
            'tax_ids',
        ] as $field) {
            if (! array_key_exists($field, $data)) {
                continue;
            }
            $data[$field] = isset($data[$field]) ? trim((string) $data[$field]) : null;
            if ($data[$field] === '') {
                $data[$field] = null;
            }
        }
        foreach ($this->otherLocales() as $locale) {
            foreach (["name_$locale", "description_$locale"] as $field) {
                if (array_key_exists($field, $data)) {
                    $data[$field] = trim((string) $data[$field]) ?: null;
                }
            }
        }
        $data['is_active'] = match (mb_strtolower((string) $data['is_active'])) {
            'true', 'yes', '1' => 1,
            'false', 'no', '0' => 0,
            default => $data['is_active'],
        };

        return $data;
    }

    private function otherLocales(): array
    {
        return array_values(array_filter(
            supportedLocaleKeys(),
            fn ($locale) => $locale !== (setting('default_locale') ?: 'en')
        ));
    }

    /** @return array{ImportOperation, ?string} */
    private function operation(ImportMode $mode, bool $exists): array
    {
        if ($exists) {
            return match ($mode) {
                ImportMode::CreateOnly => [ImportOperation::Invalid, __('product::products.import.exists')],
                ImportMode::SkipDuplicates => [ImportOperation::Skip, null],
                default => [ImportOperation::Update, null],
            };
        }

        return $mode === ImportMode::UpdateOnly
            ? [ImportOperation::Invalid, __('product::products.import.missing')]
            : [ImportOperation::Create, null];
    }
}
