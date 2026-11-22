<?php

namespace Modules\SeatingPlan\Imports;

use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Modules\Import\Enums\ImportMode;
use Modules\Import\Enums\ImportOperation;
use Modules\Import\Models\DataImport;
use Modules\Import\Support\BranchImportDefinition;
use Modules\SeatingPlan\Enums\TableShape;
use Modules\SeatingPlan\Models\Floor;
use Modules\SeatingPlan\Models\Table;
use Modules\SeatingPlan\Models\Zone;

abstract class SeatingImportDefinition extends BranchImportDefinition
{
    /**
     * Get the fields available for the seating resource import.
     *
     * The returned fields vary depending on the seating resource:
     * floors, zones, or tables.
     *
     * @return array<int, array<string, mixed>>
     */
    public function fields(): array
    {
        $resource = $this->resource();
        $assignedBranchId = $this->branches->assignedBranchId();

        $fields = [
            [
                'key' => 'id',
                'label' => __('admin::admin.table.id'),
                'type' => 'integer',
                'required' => false,
            ],
            ...$this->translatedFields(
                'name',
                $resource === 'tables'
                    ? __('seatingplan::tables.table_columns.name')
                    : __("seatingplan::$resource.table.name")
            ),
            $this->branchField(),
        ];

        if ($resource !== 'floors') {
            $floors = Floor::query()
                ->where('is_active', true)
                ->when($assignedBranchId !== null, fn ($query) => $query->where('branch_id', $assignedBranchId))
                ->when($assignedBranchId === null, fn ($query) => $query->with('branch:id,name'))
                ->orderBy('id')
                ->get(['id', 'name', 'branch_id'])
                ->map(fn (Floor $floor) => [
                    'value' => (string) $floor->id,
                    'label' => $assignedBranchId === null
                        ? $floor->name.' ('.$floor->branch?->name.')'
                        : $floor->name,
                    'matches' => ['branch_id' => (string) $floor->branch_id],
                ])->all();

            $fields[] = [
                'key' => 'floor_id',
                'label' => __('seatingplan::zones.table.floor'),
                'type' => 'integer',
                'required' => true,
                'options' => $floors,
            ];
        }

        if ($resource === 'tables') {
            $zones = Zone::query()
                ->where('is_active', true)
                ->whereHas('floor', fn ($query) => $query->where('is_active', true)->whereNull('deleted_at'))
                ->when($assignedBranchId !== null, fn ($query) => $query->where('branch_id', $assignedBranchId))
                ->with('floor:id,name')
                ->when($assignedBranchId === null, fn ($query) => $query->with('branch:id,name'))
                ->orderBy('id')
                ->get(['id', 'name', 'floor_id', 'branch_id'])
                ->map(fn (Zone $zone) => [
                    'value' => (string) $zone->id,
                    'label' => $zone->name.' ('.$zone->floor?->name
                        .($assignedBranchId === null ? ', '.$zone->branch?->name : '').')',
                    'matches' => [
                        'branch_id' => (string) $zone->branch_id,
                        'floor_id' => (string) $zone->floor_id,
                    ],
                ])->all();

            $fields[] = [
                'key' => 'zone_id',
                'label' => __('seatingplan::tables.table_columns.zone'),
                'type' => 'integer',
                'required' => true,
                'options' => $zones,
            ];

            $fields[] = [
                'key' => 'capacity',
                'label' => __('seatingplan::tables.table_columns.capacity'),
                'type' => 'integer',
                'required' => true,
            ];

            $fields[] = [
                'key' => 'shape',
                'label' => __('seatingplan::tables.table_columns.shape'),
                'type' => 'text',
                'required' => true,
                'options' => array_map(
                    fn (TableShape $shape) => [
                        'value' => $shape->value,
                        'label' => $shape->trans(),
                    ],
                    TableShape::cases()
                ),
            ];
        }

        $fields[] = [
            'key' => 'is_active',
            'label' => __('admin::admin.table.activation'),
            'type' => 'boolean',
            'required' => true,
        ];

        return $fields;
    }

    /**
     * Get the seating resource handled by this import definition.
     *
     * @return 'floors'|'zones'|'tables'
     */
    abstract protected function resource(): string;

    /**
     * Authorize the current user to import the seating resource.
     *
     * Verifies both the resource-level import permission and access
     * to the branch associated with the import.
     */
    public function authorize(?DataImport $import = null): void
    {
        abort_unless(
            auth()->user()?->can('admin.'.$this->resource().'.import'),
            403
        );

        $this->authorizeBranch($import);
    }

    /**
     * Generate a deterministic key used to identify an import row.
     *
     * When an ID is provided, the key is based on the record ID.
     * Otherwise, the key is generated from the normalized name,
     * branch, and relevant seating hierarchy.
     *
     * @param  array<string, mixed>  $data
     */
    public function key(array $data, DataImport $import): ?string
    {
        $id = trim((string) ($data['id'] ?? ''));

        if ($id !== '') {
            return 'id:'.hash('sha256', $id);
        }

        $name = mb_strtolower(trim((string) ($data['name'] ?? '')));

        $branch = $this->branches->assignedBranchId()
            ?? ($data['branch_id'] ?? '');

        $location = match ($this->resource()) {
            'floors' => '',
            'zones' => ':'.($data['floor_id'] ?? ''),
            'tables' => ':'.($data['floor_id'] ?? '').':'.($data['zone_id'] ?? ''),
        };

        return $name === ''
            ? null
            : 'name:'.hash('sha256', "$branch$location:$name");
    }

    /**
     * Validate and resolve an imported seating row.
     *
     * The method normalizes the input, validates the resource-specific
     * fields, resolves an existing target record, determines the import
     * operation, and verifies the required create or update permission.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public function validate(array $data, DataImport $import): array
    {
        $resource = $this->resource();

        $fields = [
            'id',
            'name',
            'branch_id',
            'is_active',
            'floor_id',
            'zone_id',
            'capacity',
            'shape',
        ];

        foreach ($this->otherLocales() as $locale) {
            $fields[] = "name_$locale";
        }

        $data = $this->normalize($data, $fields);

        if ($import->mode === ImportMode::CreateOnly) {
            $data['id'] = null;
        }

        $rules = [
            'id' => 'nullable|integer|min:1',
            'name' => 'required|string|max:255',
            'branch_id' => 'required|integer|exists:branches,id,deleted_at,NULL',
            'is_active' => 'required|boolean',
        ];

        foreach ($this->otherLocales() as $locale) {
            $rules["name_$locale"] = 'nullable|string|max:255';
        }

        if ($resource !== 'floors') {
            $rules['floor_id'] = [
                'required',
                'integer',
                Rule::exists('floors', 'id')
                    ->where('branch_id', $data['branch_id'])
                    ->whereNull('deleted_at')
                    ->where('is_active', 1),
            ];
        }

        if ($resource === 'tables') {
            $rules['zone_id'] = [
                'required',
                'integer',
                Rule::exists('zones', 'id')
                    ->where('branch_id', $data['branch_id'])
                    ->where('floor_id', $data['floor_id'] ?? 0)
                    ->whereNull('deleted_at')
                    ->where('is_active', 1),
            ];

            $rules['capacity'] = 'required|integer|min:1|max:100';
            $rules['shape'] = [
                'required',
                Rule::enum(TableShape::class),
            ];
        }

        $errors = Validator::make($data, $rules)
            ->errors()
            ->toArray();

        if ($errors !== []) {
            return compact('data', 'errors') + [
                'operation' => ImportOperation::Invalid,
                'target_id' => null,
            ];
        }

        $this->resolveBranch($data);

        $model = $this->model();

        $query = $model::query()
            ->withoutGlobalActive()
            ->where('branch_id', $data['branch_id']);

        if ($resource !== 'floors') {
            $query->where('floor_id', $data['floor_id']);
        }

        if ($resource === 'tables') {
            $query->where('zone_id', $data['zone_id']);
        }

        if (! empty($data['id'])) {
            $existing = $model::query()
                ->withoutGlobalActive()
                ->where('branch_id', $data['branch_id'])
                ->find($data['id']);
        } else {
            $matches = $query
                ->where(
                    'name->'.(setting('default_locale') ?: 'en'),
                    $data['name']
                )
                ->limit(2)
                ->get();

            $existing = $matches->count() === 1
                ? $matches->first()
                : null;

            if ($matches->count() > 1) {
                $errors['name'] = [
                    __('import::imports.messages.duplicate_target'),
                ];
            }
        }

        if (! empty($data['id']) && ! $existing) {
            $errors['id'] = [
                __('validation.exists', ['attribute' => 'id']),
            ];
        }

        [$operation, $modeError] = $this->operation(
            $import->mode,
            (bool) $existing
        );

        if ($modeError) {
            $errors['id'] = [$modeError];
        }

        $permission = $operation === ImportOperation::Update
            ? 'edit'
            : 'create';

        if (
            $operation !== ImportOperation::Invalid
            && $operation !== ImportOperation::Skip
            && ! auth()->user()->can("admin.$resource.$permission")
        ) {
            $errors['id'] = [
                __('import::imports.messages.cannot_'.$permission),
            ];
        }

        return compact('data', 'errors', 'operation') + [
            'target_id' => $existing?->id,
        ];
    }

    /**
     * Get the Eloquent model class associated with the seating resource.
     *
     * @return class-string<Floor|Zone|Table>
     */
    private function model(): string
    {
        return match ($this->resource()) {
            'floors' => Floor::class,
            'zones' => Zone::class,
            'tables' => Table::class,
        };
    }

    /**
     * Apply validated import data to the target seating record.
     *
     * Creates a new record when no target ID is supplied; otherwise,
     * updates the existing record within the resolved branch.
     *
     * @param  array<string, mixed>  $data
     */
    public function apply(
        array $data,
        DataImport $import,
        ?int $targetId
    ): void {
        $this->resolveBranch($data);

        $model = $this->model();

        $record = $targetId
            ? $model::query()
                ->withoutGlobalActive()
                ->where('branch_id', $data['branch_id'])
                ->findOrFail($targetId)
            : new $model;

        $values = [
            'name' => $this->translations(
                $data,
                'name',
                $targetId ? $record : null,
                $import->mode
            ),
            'is_active' => (bool) $data['is_active'],
        ];

        if (! $targetId) {
            $values['branch_id'] = $data['branch_id'];
        }

        if ($this->resource() !== 'floors') {
            $values['floor_id'] = $data['floor_id'];
        }

        if ($this->resource() === 'tables') {
            $values['zone_id'] = $data['zone_id'];
            $values['capacity'] = $data['capacity'];
            $values['shape'] = $data['shape'];
        }

        $record->fill($values)->save();
    }
}
