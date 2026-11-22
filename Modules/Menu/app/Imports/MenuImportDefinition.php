<?php

namespace Modules\Menu\Imports;

use Illuminate\Support\Facades\Validator;
use Modules\Branch\Models\Branch;
use Modules\Branch\Services\BranchAccess\BranchAccessServiceInterface;
use Modules\Import\Contracts\ImportDefinitionInterface;
use Modules\Import\Enums\ImportMode;
use Modules\Import\Enums\ImportOperation;
use Modules\Import\Models\DataImport;
use Modules\Menu\Models\Menu;

class MenuImportDefinition implements ImportDefinitionInterface
{
    public function __construct(private BranchAccessServiceInterface $branches) {}

    /** {@inheritDoc} */
    public function fields(): array
    {
        $fields = [
            [
                'key' => 'id',
                'label' => __('menu::menus.import.id'),
                'type' => 'integer',
                'required' => false,
            ],
            [
                'key' => 'name',
                'label' => __('menu::menus.table.name'),
                'type' => 'text',
                'required' => true,
            ],
            [
                'key' => 'description',
                'label' => __('menu::menus.table.description'),
                'type' => 'text',
                'required' => false,
            ],
            [
                'key' => 'branch_id',
                'label' => __('menu::menus.import.branch_id'),
                'type' => 'integer',
                'required' => $this->branches->assignedBranchId() === null,
                'options' => Branch::query()
                    ->withoutGlobalActive()
                    ->when($this->branches->assignedBranchId(), fn ($query, $id) => $query->whereKey($id))
                    ->orderBy('id')
                    ->get(['id', 'name'])
                    ->map(fn (Branch $branch) => ['value' => (string) $branch->id, 'label' => $branch->name])
                    ->all(),
            ],
            [
                'key' => 'is_active',
                'label' => __('admin::admin.table.activation'),
                'type' => 'boolean',
                'required' => true,
            ],
        ];

        foreach ($this->otherLocales() as $locale) {
            $fields[] = [
                'key' => "name_$locale",
                'label' => __('menu::menus.table.name')." ($locale)",
                'type' => 'text',
                'required' => false,
            ];
            $fields[] = [
                'key' => "description_$locale",
                'label' => __('menu::menus.table.description')." ($locale)",
                'type' => 'text',
                'required' => false,
            ];
        }

        return $fields;
    }

    /** {@inheritDoc} */
    public function authorize(?DataImport $import = null): void
    {
        $user = auth()->user();
        abort_unless($user?->can('admin.menus.import'), 403);
        if ($import && $this->branches->assignedBranchId() !== null) {
            abort_unless(($import->context['branch_id'] ?? null) === $this->branches->assignedBranchId(), 404);
        }
    }

    /** {@inheritDoc} */
    public function resolveContext(array $input): array
    {
        return ['branch_id' => $this->branches->assignedBranchId()];
    }

    /** {@inheritDoc} */
    public function key(array $data, DataImport $import): ?string
    {
        $id = trim((string) ($data['id'] ?? ''));
        if ($id !== '') {
            return 'id:'.hash('sha256', $id);
        }

        $branch = $this->branches->assignedBranchId() ?? ($data['branch_id'] ?? '');
        $name = mb_strtolower(trim((string) ($data['name'] ?? '')));

        return $name === '' ? null : 'name:'.hash('sha256', "$branch:$name");
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
            'name' => 'required|string|max:255',
            'description' => 'nullable|string|max:500',
            'branch_id' => 'required|integer|exists:branches,id,deleted_at,NULL',
            'is_active' => 'required|boolean',
        ];
        foreach ($this->otherLocales() as $locale) {
            $rules["name_$locale"] = 'nullable|string|max:255';
            $rules["description_$locale"] = 'nullable|string|max:500';
        }
        $errors = Validator::make($data, $rules, [], [
            'id' => __('menu::menus.import.id'),
            'name' => __('menu::menus.table.name'),
            'description' => __('menu::menus.table.description'),
            'branch_id' => __('menu::menus.import.branch_id'),
            'is_active' => __('admin::admin.table.activation'),
        ])
            ->errors()
            ->toArray();

        if ($errors !== []) {
            return compact('data', 'errors') + ['operation' => ImportOperation::Invalid, 'target_id' => null];
        }

        $this->branches->resolve((int) $data['branch_id'], true);
        $query = Menu::query()
            ->withoutGlobalActive()
            ->where('branch_id', $data['branch_id']);
        if (! empty($data['id'])) {
            $existing = $query->whereKey($data['id'])
                ->first();
        } else {
            $existing = $query->where('name->'.(setting('default_locale') ?: 'en'), $data['name'])
                ->first();
        }

        if (! empty($data['id']) && ! $existing) {
            $errors['id'] = [__('menu::menus.import.missing_id')];
        }

        [$operation, $modeError] = $this->operation($import->mode, (bool) $existing);
        if ($modeError) {
            $matchField = ! empty($data['id']) ? 'id' : 'name';
            $errors[$matchField] ??= [$modeError];
        }

        if ($operation === ImportOperation::Create && ! auth()->user()->can('admin.menus.create')) {
            $errors['name'] = [__('menu::menus.import.cannot_create')];
        }
        if ($operation === ImportOperation::Update && ! auth()->user()->can('admin.menus.edit')) {
            $errors['id'] = [__('menu::menus.import.cannot_update')];
        }

        return compact('data', 'errors', 'operation') + ['target_id' => $existing?->id];
    }

    /** {@inheritDoc} */
    public function apply(array $data, DataImport $import, ?int $targetId): void
    {
        $this->branches->resolve((int) $data['branch_id'], true);
        $locale = setting('default_locale') ?: 'en';
        $menu = new Menu;
        if ($targetId !== null) {
            $menu = Menu::query()
                ->withoutGlobalActive()
                ->where('branch_id', $data['branch_id'])
                ->findOrFail($targetId);
        }

        if (! $menu->exists) {
            $menu->branch_id = $data['branch_id'];
        }
        $menu->setTranslation('name', $locale, $data['name']);
        if (array_key_exists('description', $data) &&
            ($import->mode === ImportMode::ReplaceExisting || ! $menu->exists || $data['description'] !== null)) {
            $menu->setTranslation('description', $locale, $data['description'] ?? '');
        }
        foreach ($this->otherLocales() as $otherLocale) {
            foreach (['name', 'description'] as $field) {
                $key = "{$field}_$otherLocale";
                if (array_key_exists($key, $data) &&
                    ($import->mode === ImportMode::ReplaceExisting || $data[$key] !== null)) {
                    $menu->setTranslation($field, $otherLocale, $data[$key] ?? '');
                }
            }
        }
        $menu->is_active = (bool) $data['is_active'];
        $menu->save();
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

    private function normalize(array $data): array
    {
        $fields = ['id', 'name', 'description', 'branch_id', 'is_active'];
        foreach ($this->otherLocales() as $locale) {
            $fields[] = "name_$locale";
            $fields[] = "description_$locale";
        }
        foreach ($fields as $field) {
            if (! array_key_exists($field, $data)) {
                continue;
            }
            $data[$field] = isset($data[$field]) ? trim((string) $data[$field]) : null;
            if ($data[$field] === '') {
                $data[$field] = null;
            }
        }

        $data['branch_id'] = $this->branches->assignedBranchId() ?? $data['branch_id'];
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

    /**
     * Resolve the row action while preserving the selected conflict behavior.
     *
     * @return array{ImportOperation, ?string}
     */
    private function operation(ImportMode $mode, bool $exists): array
    {
        if ($exists) {
            return match ($mode) {
                ImportMode::CreateOnly => [ImportOperation::Invalid, __('menu::menus.import.exists')],
                ImportMode::SkipDuplicates => [ImportOperation::Skip, null],
                default => [ImportOperation::Update, null],
            };
        }

        return $mode === ImportMode::UpdateOnly
            ? [ImportOperation::Invalid, __('menu::menus.import.missing')]
            : [ImportOperation::Create, null];
    }
}
