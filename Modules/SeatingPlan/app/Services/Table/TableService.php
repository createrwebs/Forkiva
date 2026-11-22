<?php

namespace Modules\SeatingPlan\Services\Table;

use App\Forkiva;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Modules\Branch\Models\Branch;
use Modules\Branch\Services\BranchAccess\BranchAccessServiceInterface;
use Modules\SeatingPlan\Enums\TableQrEventType;
use Modules\SeatingPlan\Enums\TableShape;
use Modules\SeatingPlan\Enums\TableStatus;
use Modules\SeatingPlan\Models\Floor;
use Modules\SeatingPlan\Models\Table;
use Modules\SeatingPlan\Models\TableQrEvent;
use Modules\SeatingPlan\Models\Zone;
use Modules\Support\GlobalStructureFilters;

class TableService implements TableServiceInterface
{
    public function __construct(protected BranchAccessServiceInterface $branchAccess) {}

    /** {@inheritDoc} */
    public function label(): string
    {
        return __('seatingplan::tables.table');
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
            ->withoutGlobalActive()
            ->with(['branch:id,name', 'floor:id,name', 'zone:id,name'])
            ->filters($filters)
            ->sortBy($sorts);
    }

    /** {@inheritDoc} */
    public function exportResourceKey(): string
    {
        return 'tables';
    }

    /** {@inheritDoc} */
    public function exportPermission(): string
    {
        return 'admin.tables.export';
    }

    /** {@inheritDoc} */
    public function exportModelClass(): string
    {
        return Table::class;
    }

    /** {@inheritDoc} */
    public function getModel(): Table
    {
        return new ($this->model());
    }

    /** {@inheritDoc} */
    public function model(): string
    {
        return Table::class;
    }

    /** {@inheritDoc} */
    public function show(int $id): Table
    {
        return $this->getModel()
            ->query()
            ->with(['branch:id,name', 'floor:id,name', 'zone:id,name'])
            ->withoutGlobalActive()
            ->findOrFail($id);
    }

    /** {@inheritDoc} */
    public function findOrFail(int $id, bool $withBranch = false): Builder|array|EloquentCollection|Table
    {
        return $this->getModel()
            ->query()
            ->when($withBranch, fn ($query) => $query->with('branch'))
            ->withoutGlobalActive()
            ->findOrFail($id);
    }

    /** {@inheritDoc} */
    public function store(array $data): Table
    {
        return $this->getModel()->query()->create($data);
    }

    /** {@inheritDoc} */
    public function update(int $id, array $data): Table
    {
        $table = $this->findOrFail($id);
        $table->update($data);

        return $table;
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
    public function getStructureFilters(?int $branchId = null, ?int $floorId = null): array
    {
        $branchFilter = GlobalStructureFilters::branch();

        return [
            ...(is_null($branchFilter) ? [] : [$branchFilter]),
            [
                'key' => 'floor_id',
                'label' => __('seatingplan::tables.filters.floor'),
                'type' => 'select',
                'options' => ! is_null($branchId) ? Floor::list($branchId) : [],
                'depends' => 'branch_id',
            ],
            [
                'key' => 'zone_id',
                'label' => __('seatingplan::tables.filters.zone'),
                'type' => 'select',
                'options' => ! is_null($branchId) && ! is_null($floorId) ? Zone::list($branchId, $floorId) : [],
                'depends' => 'floor_id',
            ],
            [
                'key' => 'status',
                'label' => __('admin::admin.filters.status'),
                'type' => 'select',
                'options' => TableStatus::toArrayTrans(),
            ],
            [
                'key' => 'shape',
                'label' => __('seatingplan::tables.filters.shape'),
                'type' => 'select',
                'options' => TableShape::toArrayTrans(),
            ],
            GlobalStructureFilters::active(),
            GlobalStructureFilters::from(),
            GlobalStructureFilters::to(),
        ];
    }

    /** {@inheritDoc} */
    public function getFormMeta(?int $branchId = null, ?int $floorId = null): array
    {
        $effectiveBranch = $this->branchAccess->resolve($branchId);

        if (is_null($branchId)) {
            return [
                'branches' => Branch::list(true),
                'shapes' => TableShape::toArrayTrans(),
            ];
        } elseif ($effectiveBranch && is_null($floorId)) {
            return [
                'floors' => Floor::list($effectiveBranch),
            ];
        } elseif ($effectiveBranch && $floorId) {
            return [
                'zones' => Zone::list($effectiveBranch, $floorId),
            ];
        }

        return [];
    }

    /** {@inheritDoc} */
    public function getStatusLogs($id): LengthAwarePaginator
    {
        return $this->findOrFail($id)
            ->statusLogs()
            ->with(['changedBy:id,name'])
            ->paginate(Forkiva::paginate())
            ->withQueryString();
    }

    /** {@inheritDoc} */
    public function regenerateQrToken(int $id): Table
    {
        $table = $this->findOrFail($id);
        $table->regenerateQrToken();

        TableQrEvent::query()->create([
            'table_id' => $table->id,
            'event_type' => TableQrEventType::TokenRegenerated,
            'created_by' => auth()->id(),
            'ip_address' => request()->ip(),
            'user_agent' => request()->userAgent(),
        ]);

        return $table->fresh(['branch:id,name', 'floor:id,name', 'zone:id,name']);
    }
}
