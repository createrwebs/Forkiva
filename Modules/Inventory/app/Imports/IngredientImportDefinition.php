<?php

namespace Modules\Inventory\Imports;

use Illuminate\Support\Facades\Validator;
use Modules\Branch\Services\BranchAccess\BranchAccessServiceInterface;
use Modules\Import\Enums\ImportMode;
use Modules\Import\Enums\ImportOperation;
use Modules\Import\Models\DataImport;
use Modules\Inventory\Models\Ingredient;
use Modules\Inventory\Models\StockMovement;
use Modules\Inventory\Models\Unit;
use Modules\Inventory\Services\Ingredient\IngredientServiceInterface;

class IngredientImportDefinition extends BranchInventoryImportDefinition
{
    public function __construct(BranchAccessServiceInterface $branches, private IngredientServiceInterface $ingredients)
    {
        parent::__construct($branches);
    }

    /** {@inheritDoc} */
    public function fields(): array
    {
        $fields = [
            ['key' => 'id', 'label' => __('admin::admin.table.id'), 'type' => 'integer', 'required' => false],
            ['key' => 'name', 'label' => __('inventory::ingredients.table.name'), 'type' => 'text', 'required' => true],
            $this->branchField(),
            ['key' => 'unit_id', 'label' => __('inventory::ingredients.table.unit'), 'type' => 'integer', 'required' => true,
                'options' => Unit::query()->orderBy('id')->get(['id', 'name', 'symbol'])
                    ->map(fn (Unit $unit) => ['value' => (string) $unit->id, 'label' => "$unit->name ($unit->symbol)"])->all()],
            ['key' => 'cost_per_unit', 'label' => __('inventory::ingredients.table.cost_per_unit'), 'type' => 'text', 'required' => true],
            ['key' => 'alert_quantity', 'label' => __('inventory::ingredients.table.alert_quantity'), 'type' => 'text', 'required' => true],
            ['key' => 'is_returnable', 'label' => __('inventory::ingredients.table.is_returnable'), 'type' => 'boolean', 'required' => true],
        ];
        foreach ($this->otherLocales() as $locale) {
            $fields[] = ['key' => "name_$locale", 'label' => __('inventory::ingredients.table.name')." ($locale)", 'type' => 'text', 'required' => false];
        }

        return $fields;
    }

    /** {@inheritDoc} */
    public function authorize(?DataImport $import = null): void
    {
        abort_unless(auth()->user()?->can('admin.ingredients.import'), 403);
        $this->authorizeBranch($import);
    }

    /** {@inheritDoc} */
    public function key(array $data, DataImport $import): ?string
    {
        $id = trim((string) ($data['id'] ?? ''));
        $name = mb_strtolower(trim((string) ($data['name'] ?? '')));
        $branch = $this->branches->assignedBranchId() ?? ($data['branch_id'] ?? '');

        return $id !== '' ? 'id:'.hash('sha256', $id) : ($name === '' ? null : 'name:'.hash('sha256', "$branch:$name"));
    }

    /** {@inheritDoc} */
    public function validate(array $data, DataImport $import): array
    {
        $fields = ['id', 'name', 'branch_id', 'unit_id', 'cost_per_unit', 'alert_quantity', 'is_returnable'];
        foreach ($this->otherLocales() as $locale) {
            $fields[] = "name_$locale";
        }
        $data = $this->normalizeBranch($this->normalized($data, $fields));
        $data['is_returnable'] = $this->boolean($data['is_returnable'] ?? null);
        if ($import->mode === ImportMode::CreateOnly) {
            $data['id'] = null;
        }
        $rules = [
            'id' => 'nullable|integer|min:1',
            'name' => 'required|string|max:255',
            'branch_id' => 'required|integer|exists:branches,id,deleted_at,NULL',
            'unit_id' => 'required|integer|exists:units,id,deleted_at,NULL',
            'cost_per_unit' => 'required|numeric|min:0|max:99999.9999',
            'alert_quantity' => 'required|numeric|min:0|max:100000',
            'is_returnable' => 'required|boolean',
        ];
        foreach ($this->otherLocales() as $locale) {
            $rules["name_$locale"] = 'nullable|string|max:255';
        }
        $errors = Validator::make($data, $rules)->errors()->toArray();
        if ($errors !== []) {
            return compact('data', 'errors') + ['operation' => ImportOperation::Invalid, 'target_id' => null];
        }
        $this->resolveBranch($data);
        $query = Ingredient::query()->where('branch_id', $data['branch_id']);
        $existing = ! empty($data['id'])
            ? (clone $query)->whereKey($data['id'])->first()
            : (clone $query)->where('name->'.(setting('default_locale') ?: 'en'), $data['name'])->first();
        if (! empty($data['id']) && ! $existing) {
            $errors['id'] = [__('inventory::ingredients.import.missing_id')];
        }
        [$operation, $modeError] = $this->operation($import->mode, (bool) $existing, 'ingredients');
        if ($modeError) {
            $errors[! empty($data['id']) ? 'id' : 'name'] = [$modeError];
        }
        if ($existing && (int) $existing->unit_id !== (int) $data['unit_id']
            && StockMovement::query()->withOutGlobalBranchPermission()->where('ingredient_id', $existing->id)->exists()) {
            $errors['unit_id'] = [__('inventory::messages.ingredient_stock_unit_immutable')];
        }

        return compact('data', 'errors', 'operation') + ['target_id' => $existing?->id];
    }

    /** {@inheritDoc} */
    public function apply(array $data, DataImport $import, ?int $targetId): void
    {
        $this->resolveBranch($data);
        $ingredient = $targetId
            ? Ingredient::query()->where('branch_id', $data['branch_id'])->findOrFail($targetId)
            : new Ingredient;
        $name = $ingredient->getTranslations('name');
        $name[setting('default_locale') ?: 'en'] = $data['name'];
        foreach ($this->otherLocales() as $locale) {
            $key = "name_$locale";
            if (array_key_exists($key, $data) && ($data[$key] !== null || $import->mode === ImportMode::ReplaceExisting)) {
                $name[$locale] = $data[$key] ?? '';
            }
        }
        $values = [
            'name' => $name,
            'unit_id' => $data['unit_id'],
            'cost_per_unit' => $data['cost_per_unit'],
            'alert_quantity' => $data['alert_quantity'],
            'is_returnable' => (bool) $data['is_returnable'],
        ];
        if ($targetId === null) {
            $this->ingredients->store($values + ['branch_id' => $data['branch_id']]);
        } else {
            $this->ingredients->update($targetId, $values);
        }
    }
}
