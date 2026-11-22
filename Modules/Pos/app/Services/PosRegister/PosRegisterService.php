<?php

namespace Modules\Pos\Services\PosRegister;

use App\Forkiva;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Modules\Branch\Models\Branch;
use Modules\Branch\Services\BranchAccess\BranchAccessServiceInterface;
use Modules\Pos\Models\PosRegister;
use Modules\Printer\Models\Printer;
use Modules\Support\GlobalStructureFilters;

class PosRegisterService implements PosRegisterServiceInterface
{
    public function __construct(protected BranchAccessServiceInterface $branchAccess) {}

    /** {@inheritDoc} */
    public function label(): string
    {
        return __('pos::pos_registers.pos_register');
    }

    /** {@inheritDoc} */
    public function get(array $filters = [], ?array $sorts = []): LengthAwarePaginator
    {
        return $this->exportQuery($filters, $sorts)
            ->paginate(Forkiva::paginate())
            ->withQueryString();
    }

    /** {@inheritDoc} */
    public function exportQuery(array $filters = [], ?array $sorts = [], array $context = []): Builder
    {
        return $this->getModel()
            ->query()
            ->with([
                'branch:id,name',
                'invoicePrinter:id,name',
                'billPrinter:id,name',
                'deliveryPrinter:id,name',
                'waiterPrinter:id,name',
            ])
            ->withoutGlobalActive()
            ->filters($filters)
            ->sortBy($sorts);
    }

    /** {@inheritDoc} */
    public function exportResourceKey(): string
    {
        return 'pos_registers';
    }

    /** {@inheritDoc} */
    public function exportPermission(): string
    {
        return 'admin.pos_registers.export';
    }

    /** {@inheritDoc} */
    public function exportModelClass(): string
    {
        return PosRegister::class;
    }

    /** {@inheritDoc} */
    public function getModel(): PosRegister
    {
        return new ($this->model());
    }

    /** {@inheritDoc} */
    public function model(): string
    {
        return PosRegister::class;
    }

    /** {@inheritDoc} */
    public function show(int $id): PosRegister
    {
        return $this->findOrFail($id);
    }

    /** {@inheritDoc} */
    public function findOrFail(int $id): Builder|array|EloquentCollection|PosRegister
    {
        return $this->getModel()
            ->query()
            ->withoutGlobalActive()
            ->findOrFail($id);
    }

    /** {@inheritDoc} */
    public function store(array $data): PosRegister
    {
        return $this->getModel()->query()->create($data);
    }

    /** {@inheritDoc} */
    public function update(int $id, array $data): PosRegister
    {
        $posRegister = $this->findOrFail($id);
        $posRegister->update($data);

        return $posRegister;
    }

    /** {@inheritDoc} */
    public function destroy(int|array|string $ids): bool
    {
        return $this->getModel()
            ->query()
            ->withoutGlobalActive()
            ->whereIn('id', parseIds($ids))
            ->delete() ?: false;
    }

    /** {@inheritDoc} */
    public function getStructureFilters(): array
    {
        $branchFilter = GlobalStructureFilters::branch();

        return [
            ...(is_null($branchFilter) ? [] : [$branchFilter]),
            GlobalStructureFilters::active(),
            GlobalStructureFilters::from(),
            GlobalStructureFilters::to(),
        ];
    }

    /** {@inheritDoc} */
    public function getFormMeta(?int $branchId = null): array
    {
        $resolvedBranchId = $this->branchAccess->resolve($branchId);

        if (is_null($branchId)) {
            return [
                'branches' => Branch::list(true),
            ];
        }

        return [
            'printers' => Printer::list($resolvedBranchId),
        ];
    }
}
