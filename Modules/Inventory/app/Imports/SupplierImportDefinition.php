<?php

namespace Modules\Inventory\Imports;

use Illuminate\Support\Facades\Validator;
use Modules\Branch\Services\BranchAccess\BranchAccessServiceInterface;
use Modules\Import\Enums\ImportMode;
use Modules\Import\Enums\ImportOperation;
use Modules\Import\Models\DataImport;
use Modules\Inventory\Models\Supplier;
use Modules\Inventory\Services\Supplier\SupplierServiceInterface;

class SupplierImportDefinition extends BranchInventoryImportDefinition
{
    public function __construct(BranchAccessServiceInterface $branches, private SupplierServiceInterface $suppliers)
    {
        parent::__construct($branches);
    }

    /** {@inheritDoc} */
    public function fields(): array
    {
        return [
            ['key' => 'id', 'label' => __('admin::admin.table.id'), 'type' => 'integer', 'required' => false],
            ['key' => 'name', 'label' => __('inventory::suppliers.table.name'), 'type' => 'text', 'required' => true],
            $this->branchField(),
            ['key' => 'phone', 'label' => __('inventory::suppliers.table.phone'), 'type' => 'text', 'required' => false],
            ['key' => 'email', 'label' => __('inventory::suppliers.table.email'), 'type' => 'text', 'required' => false],
            ['key' => 'address', 'label' => __('inventory::suppliers.table.address'), 'type' => 'text', 'required' => false],
        ];
    }

    /** {@inheritDoc} */
    public function authorize(?DataImport $import = null): void
    {
        abort_unless(auth()->user()?->can('admin.suppliers.import'), 403);
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
        $data = $this->normalizeBranch($this->normalized($data, ['id', 'name', 'branch_id', 'phone', 'email', 'address']));
        if ($import->mode === ImportMode::CreateOnly) {
            $data['id'] = null;
        }
        $errors = Validator::make($data, [
            'id' => 'nullable|integer|min:1',
            'name' => 'required|string|max:255',
            'branch_id' => 'required|integer|exists:branches,id,deleted_at,NULL',
            'phone' => 'nullable|string|max:255',
            'email' => 'nullable|email|max:255',
            'address' => 'nullable|string|max:255',
        ])->errors()->toArray();
        if ($errors !== []) {
            return compact('data', 'errors') + ['operation' => ImportOperation::Invalid, 'target_id' => null];
        }
        $this->resolveBranch($data);
        $query = Supplier::query()->where('branch_id', $data['branch_id']);
        $existing = ! empty($data['id'])
            ? (clone $query)->whereKey($data['id'])->first()
            : (clone $query)->where('name', $data['name'])->first();
        if (! empty($data['id']) && ! $existing) {
            $errors['id'] = [__('inventory::suppliers.import.missing_id')];
        }
        [$operation, $modeError] = $this->operation($import->mode, (bool) $existing, 'suppliers');
        if ($modeError) {
            $errors[! empty($data['id']) ? 'id' : 'name'] = [$modeError];
        }

        return compact('data', 'errors', 'operation') + ['target_id' => $existing?->id];
    }

    /** {@inheritDoc} */
    public function apply(array $data, DataImport $import, ?int $targetId): void
    {
        $this->resolveBranch($data);
        $values = ['name' => $data['name']];
        foreach (['phone', 'email', 'address'] as $field) {
            if (array_key_exists($field, $data) && ($targetId === null || $data[$field] !== null || $import->mode === ImportMode::ReplaceExisting)) {
                $values[$field] = $data[$field];
            }
        }
        if ($targetId === null) {
            $this->suppliers->store($values + ['branch_id' => $data['branch_id']]);
        } else {
            Supplier::query()->where('branch_id', $data['branch_id'])->findOrFail($targetId);
            $this->suppliers->update($targetId, $values);
        }
    }
}
