<?php

namespace Modules\Inventory\Imports;

use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Modules\Import\Enums\ImportMode;
use Modules\Import\Enums\ImportOperation;
use Modules\Import\Models\DataImport;
use Modules\Inventory\Enums\UnitType;
use Modules\Inventory\Models\Unit;
use Modules\Inventory\Services\Unit\UnitServiceInterface;

class UnitImportDefinition extends InventoryImportDefinition
{
    public function __construct(private UnitServiceInterface $units) {}

    /** {@inheritDoc} */
    public function fields(): array
    {
        $fields = [
            ['key' => 'id', 'label' => __('admin::admin.table.id'), 'type' => 'integer', 'required' => false],
            ['key' => 'name', 'label' => __('inventory::units.table.name'), 'type' => 'text', 'required' => true],
            ['key' => 'symbol', 'label' => __('inventory::units.table.symbol'), 'type' => 'text', 'required' => true],
            ['key' => 'type', 'label' => __('inventory::units.table.type'), 'type' => 'text', 'required' => true,
                'options' => array_map(fn (UnitType $type) => ['value' => $type->value, 'label' => $type->trans()], UnitType::cases())],
        ];
        foreach ($this->otherLocales() as $locale) {
            foreach (['name', 'symbol'] as $field) {
                $fields[] = ['key' => "{$field}_$locale", 'label' => __("inventory::units.table.$field")." ($locale)", 'type' => 'text', 'required' => false];
            }
        }

        return $fields;
    }

    /** {@inheritDoc} */
    public function authorize(?DataImport $import = null): void
    {
        abort_unless(auth()->user()?->can('admin.units.import'), 403);
    }

    /** {@inheritDoc} */
    public function resolveContext(array $input): array
    {
        return [];
    }

    /** {@inheritDoc} */
    public function key(array $data, DataImport $import): ?string
    {
        $id = trim((string) ($data['id'] ?? ''));
        $symbol = mb_strtolower(trim((string) ($data['symbol'] ?? '')));

        return $id !== '' ? 'id:'.hash('sha256', $id) : ($symbol === '' ? null : 'symbol:'.hash('sha256', $symbol));
    }

    /** {@inheritDoc} */
    public function validate(array $data, DataImport $import): array
    {
        $fields = ['id', 'name', 'symbol', 'type'];
        foreach ($this->otherLocales() as $locale) {
            $fields[] = "name_$locale";
            $fields[] = "symbol_$locale";
        }
        $data = $this->normalized($data, $fields);
        if ($import->mode === ImportMode::CreateOnly) {
            $data['id'] = null;
        }
        $rules = [
            'id' => 'nullable|integer|min:1',
            'name' => 'required|string|max:255',
            'symbol' => 'required|string|max:10',
            'type' => ['required', Rule::enum(UnitType::class)],
        ];
        foreach ($this->otherLocales() as $locale) {
            $rules["name_$locale"] = 'nullable|string|max:255';
            $rules["symbol_$locale"] = 'nullable|string|max:10';
        }
        $errors = Validator::make($data, $rules)->errors()->toArray();
        if ($errors !== []) {
            return compact('data', 'errors') + ['operation' => ImportOperation::Invalid, 'target_id' => null];
        }

        $existing = ! empty($data['id'])
            ? Unit::query()->find($data['id'])
            : Unit::query()->where('symbol->'.(setting('default_locale') ?: 'en'), $data['symbol'])->first();
        if (! empty($data['id']) && ! $existing) {
            $errors['id'] = [__('inventory::units.import.missing_id')];
        }
        [$operation, $modeError] = $this->operation($import->mode, (bool) $existing, 'units');
        if ($modeError) {
            $errors[! empty($data['id']) ? 'id' : 'symbol'] = [$modeError];
        }

        return compact('data', 'errors', 'operation') + ['target_id' => $existing?->id];
    }

    /** {@inheritDoc} */
    public function apply(array $data, DataImport $import, ?int $targetId): void
    {
        $unit = $targetId ? $this->units->show($targetId) : new Unit;
        $locale = setting('default_locale') ?: 'en';
        foreach (['name', 'symbol'] as $field) {
            $unit->setTranslation($field, $locale, $data[$field]);
            foreach ($this->otherLocales() as $otherLocale) {
                $key = "{$field}_$otherLocale";
                if (array_key_exists($key, $data) && ($data[$key] !== null || $import->mode === ImportMode::ReplaceExisting)) {
                    $unit->setTranslation($field, $otherLocale, $data[$key] ?? '');
                }
            }
        }
        $unit->type = $data['type'];
        $unit->save();
    }
}
