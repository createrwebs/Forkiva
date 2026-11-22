<?php

namespace Modules\Inventory\Imports;

use Modules\Branch\Models\Branch;
use Modules\Branch\Services\BranchAccess\BranchAccessServiceInterface;
use Modules\Import\Models\DataImport;

abstract class BranchInventoryImportDefinition extends InventoryImportDefinition
{
    public function __construct(protected BranchAccessServiceInterface $branches) {}

    /**
     * Show only branches available to the importer; assigned users do not need a branch column.
     *
     * @return array{key: string, label: string, type: string, required: bool, options: array<int, array{value: string, label: string}>}
     */
    protected function branchField(): array
    {
        return [
            'key' => 'branch_id',
            'label' => __('branch::branches.table.branch'),
            'type' => 'integer',
            'required' => $this->branches->assignedBranchId() === null,
            'options' => Branch::query()->withoutGlobalActive()
                ->when($this->branches->assignedBranchId(), fn ($query, $id) => $query->whereKey($id))
                ->orderBy('id')->get(['id', 'name'])
                ->map(fn (Branch $branch) => ['value' => (string) $branch->id, 'label' => $branch->name])->all(),
        ];
    }

    /** {@inheritDoc} */
    public function resolveContext(array $input): array
    {
        return ['branch_id' => $this->branches->assignedBranchId()];
    }

    /**
     * Reject an import if the actor's assigned branch differs from its saved context.
     */
    protected function authorizeBranch(?DataImport $import): void
    {
        if ($import && $this->branches->assignedBranchId() !== null) {
            abort_unless(($import->context['branch_id'] ?? null) === $this->branches->assignedBranchId(), 404);
        }
    }

    /**
     * Use the assigned branch instead of a branch ID supplied by the spreadsheet.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function normalizeBranch(array $data): array
    {
        $data['branch_id'] = $this->branches->assignedBranchId() ?? ($data['branch_id'] ?? null);

        return $data;
    }

    /**
     * Check that an unassigned administrator selected an accessible branch.
     *
     * @param  array<string, mixed>  $data
     */
    protected function resolveBranch(array $data): void
    {
        $this->branches->resolve((int) $data['branch_id'], true);
    }
}
