<?php

namespace Modules\Reservation\Services\ReservationTableBlock;

use App\Forkiva;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Modules\Branch\Services\BranchAccess\BranchAccessServiceInterface;
use Modules\Reservation\Models\Reservation;
use Modules\Reservation\Models\ReservationTableBlock;
use Modules\Reservation\Services\ReservationSetting\ReservationSettingServiceInterface;
use Modules\SeatingPlan\Models\Table;
use Modules\Support\GlobalStructureFilters;

class ReservationTableBlockService implements ReservationTableBlockServiceInterface
{
    /**
     * Create the table block service with branch access and reservation settings dependencies.
     */
    public function __construct(
        protected BranchAccessServiceInterface $branchAccess,
        protected ReservationSettingServiceInterface $settings,
    ) {}

    /** {@inheritDoc} */
    public function label(): string
    {
        return __('reservation::reservation_table_blocks.reservation_table_block');
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
        return ReservationTableBlock::query()
            ->with(['branch:id,name', 'table:id,name,capacity,floor_id,zone_id', 'table.floor:id,name', 'table.zone:id,name', 'createdBy:id,name'])
            ->filters($filters)
            ->sortBy($sorts ?: [['key' => 'start_at', 'order' => 'desc']]);
    }

    /** {@inheritDoc} */
    public function exportResourceKey(): string
    {
        return 'reservation_table_blocks';
    }

    /** {@inheritDoc} */
    public function exportPermission(): string
    {
        return 'admin.reservation_table_blocks.export';
    }

    /** {@inheritDoc} */
    public function exportModelClass(): string
    {
        return ReservationTableBlock::class;
    }

    /** {@inheritDoc} */
    public function show(int $id): ReservationTableBlock
    {
        return ReservationTableBlock::query()
            ->with(['branch:id,name', 'table:id,name,capacity,floor_id,zone_id', 'table.floor:id,name', 'table.zone:id,name', 'createdBy:id,name'])
            ->findOrFail($id);
    }

    /** {@inheritDoc} */
    public function store(array $data): ReservationTableBlock
    {
        $data['branch_id'] = $this->branchAccess->resolve((int) $data['branch_id'], required: true);
        $this->assertValidBlock($data);

        return ReservationTableBlock::query()
            ->create($this->payload($data))
            ->load(['branch:id,name', 'table:id,name,capacity,floor_id,zone_id', 'table.floor:id,name', 'table.zone:id,name', 'createdBy:id,name']);
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function payload(array $data, ?ReservationTableBlock $block = null): array
    {
        return [
            'branch_id' => $data['branch_id'] ?? $block?->branch_id,
            'table_id' => $data['table_id'] ?? $block?->table_id,
            'title' => $data['title'] ?? $block?->title,
            'reason' => $data['reason'] ?? $block?->reason,
            'start_at' => $data['start_at'] ?? $block?->start_at,
            'end_at' => $data['end_at'] ?? $block?->end_at,
        ];
    }

    /** {@inheritDoc} */
    public function update(int $id, array $data): ReservationTableBlock
    {
        $block = ReservationTableBlock::query()->findOrFail($id);
        $payload = $this->payload($data, $block);
        $this->assertValidBlock($payload, $block);
        $block->update($payload);

        return $block->load(['branch:id,name', 'table:id,name,capacity,floor_id,zone_id', 'table.floor:id,name', 'table.zone:id,name', 'createdBy:id,name']);
    }

    /** {@inheritDoc} */
    public function destroy(int|string|array $ids): bool
    {
        return ReservationTableBlock::query()->whereIn('id', parseIds($ids))->delete() ?: false;
    }

    /** {@inheritDoc} */
    public function getStructureFilters(): array
    {
        $branchFilter = GlobalStructureFilters::branch();

        return [
            ...(is_null($branchFilter) ? [] : [$branchFilter]),
            GlobalStructureFilters::from(),
            GlobalStructureFilters::to(),
        ];
    }

    /**
     * Ensure a table block is branch-consistent and does not silently conflict
     * with an existing active reservation.
     *
     * @param  array<string, mixed>  $data
     */
    protected function assertValidBlock(array $data, ?ReservationTableBlock $existing = null): void
    {
        $branchId = (int) $data['branch_id'];
        $tableId = filled($data['table_id'] ?? null) ? (int) $data['table_id'] : null;

        if ($tableId) {
            Table::query()
                ->withOutGlobalBranchPermission()
                ->withoutGlobalActive()
                ->where('branch_id', $branchId)
                ->where('is_active', true)
                ->findOrFail($tableId);
        }

        $scheduleChanged = ! $existing
            || (int) $existing->table_id !== (int) $tableId
            || $existing->start_at?->toDateTimeString() !== (string) $data['start_at']
            || $existing->end_at?->toDateTimeString() !== (string) $data['end_at'];
        if (! $scheduleChanged) {
            return;
        }

        $setting = $this->settings->forBranch($branchId);
        $hasConflict = Reservation::query()
            ->withOutGlobalBranchPermission()
            ->where('branch_id', $branchId)
            ->activeForAvailability($setting->pending_blocks_capacity)
            ->where('start_at', '<', $data['end_at'])
            ->where('end_at', '>', $data['start_at'])
            ->whereHas('tables', fn (Builder $query) => $query->when(
                $tableId,
                fn (Builder $query) => $query->where('tables.id', $tableId),
            ))
            ->exists();

        abort_if($hasConflict, 422, __('reservation::messages.table_block_conflicts_with_reservation'));
    }
}
