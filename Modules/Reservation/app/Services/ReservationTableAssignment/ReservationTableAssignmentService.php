<?php

namespace Modules\Reservation\Services\ReservationTableAssignment;

use DB;
use Illuminate\Database\Eloquent\Collection;
use Modules\Reservation\Enums\ReservationStatus;
use Modules\Reservation\Enums\TableAssignmentMode;
use Modules\Reservation\Enums\TableAssignmentStatus;
use Modules\Reservation\Events\ReservationTablesAssigned;
use Modules\Reservation\Models\Reservation;
use Modules\Reservation\Services\ReservationAvailability\ReservationAvailabilityServiceInterface;
use Modules\SeatingPlan\Enums\TableStatus;
use Modules\SeatingPlan\Models\Table;
use Throwable;

class ReservationTableAssignmentService implements ReservationTableAssignmentServiceInterface
{
    /**
     * Create the assignment service with table availability checks.
     */
    public function __construct(protected ReservationAvailabilityServiceInterface $availability) {}

    /** {@inheritDoc} */
    public function autoAssign(int|string|Reservation $reservation): Reservation
    {
        $reservation = $this->resolve($reservation);
        $this->assertCanAssign($reservation);

        $suggestions = $this->availability->suggestedTables([
            'branch_id' => $reservation->branch_id,
            'reservation_date' => $reservation->reservation_date->toDateString(),
            'start_time' => $reservation->start_time,
            'duration_minutes' => $reservation->duration_minutes,
            'guest_count' => $reservation->guest_count,
            'exclude_reservation_id' => $reservation->id,
        ]);

        abort_if(empty($suggestions), 422, __('reservation::messages.no_available_tables'));

        return $this->assignWithMode(
            $reservation,
            $suggestions[0]['table_ids'],
            TableAssignmentMode::AutoAssign,
        );
    }

    /**
     * Resolve reservation argument.
     */
    protected function resolve(int|string|Reservation $reservation): Reservation
    {
        if ($reservation instanceof Reservation) {
            return $reservation->loadMissing('tables');
        }

        return Reservation::query()->with(['tables', 'reservationTables.table', 'branch', 'createdBy'])->findOrFail($reservation);
    }

    /**
     * Assert reservation status allows table assignment changes.
     */
    protected function assertCanAssign(Reservation $reservation): void
    {
        abort_unless($reservation->status->allowsTableAssignment(), 422, __('reservation::messages.reservation_table_assignment_locked'));
    }

    /**
     * Assign tables atomically and preserve the requested assignment mode.
     *
     * @throws Throwable
     */
    protected function assignWithMode(
        int|string|Reservation $reservation,
        array $tableIds,
        TableAssignmentMode $mode,
    ): Reservation {
        $reservationId = $reservation instanceof Reservation ? $reservation->id : $reservation;
        $tableIds = array_values(array_unique(array_map('intval', $tableIds)));
        abort_if(empty($tableIds), 422, __('reservation::messages.tables_required'));

        return DB::transaction(function () use ($reservationId, $tableIds, $mode) {
            $reservation = Reservation::query()
                ->with(['tables', 'reservationTables.table', 'branch', 'createdBy'])
                ->lockForUpdate()
                ->findOrFail($reservationId);
            $this->assertCanAssign($reservation);
            $previousTableIds = $reservation->tables->modelKeys();

            $lockedTables = Table::query()
                ->withOutGlobalBranchPermission()
                ->withoutGlobalActive()
                ->where('branch_id', $reservation->branch_id)
                ->whereIn('id', array_values(array_unique([...$previousTableIds, ...$tableIds])))
                ->orderBy('id')
                ->lockForUpdate()
                ->get();
            abort_unless(
                $lockedTables->whereIn('id', $tableIds)->count() === count($tableIds),
                422,
                __('reservation::messages.tables_unavailable'),
            );

            $check = $this->availability->check([
                'branch_id' => $reservation->branch_id,
                'reservation_date' => $reservation->reservation_date->toDateString(),
                'start_time' => $reservation->start_time,
                'duration_minutes' => $reservation->duration_minutes,
                'guest_count' => $reservation->guest_count,
                'table_ids' => $tableIds,
                'exclude_reservation_id' => $reservation->id,
            ]);

            abort_unless($check['available'], 422, $check['warnings'][0] ?? __('reservation::messages.tables_unavailable'));

            $reservation->tables()->syncWithPivotValues($tableIds, [
                'assigned_at' => now(),
                'assigned_by' => auth()->id(),
            ]);

            $reservation->update([
                'table_assignment_mode' => $mode,
                'table_assignment_status' => TableAssignmentStatus::Assigned,
            ]);

            $reservation = $reservation->fresh(['tables', 'reservationTables.table', 'reservationTables.assignedBy', 'statusLogs', 'branch', 'createdBy']);
            $this->syncAssignedTableStates(
                $reservation,
                $lockedTables->whereIn('id', array_diff($previousTableIds, $tableIds)),
            );
            event(new ReservationTablesAssigned($reservation));

            return $reservation;
        });
    }

    /**
     * Keep the live seating-plan state consistent when tables are assigned.
     *
     * @param  Collection<int, Table>  $removedTables
     */
    protected function syncAssignedTableStates(Reservation $reservation, Collection $removedTables): void
    {
        $reservation->tables->merge($removedTables)->loadExists('activeOrder');

        foreach ($removedTables as $table) {
            $this->releaseRemovedTable($reservation, $table, $table->active_order_exists);
        }

        $targetStatus = match ($reservation->status) {
            ReservationStatus::Confirmed, ReservationStatus::Arrived => TableStatus::Reserved,
            ReservationStatus::Seated => TableStatus::Occupied,
            default => null,
        };
        if (! $targetStatus) {
            return;
        }

        foreach ($reservation->tables as $table) {
            if ($table->status !== $targetStatus && ! $table->active_order_exists) {
                $table->updateStatusWithEvent(
                    $targetStatus,
                    auth()->id(),
                    "RESERVATION:$reservation->reservation_number",
                );
            }
        }
    }

    /**
     * Release a detached table only when its live state matches this reservation lifecycle.
     */
    protected function releaseRemovedTable(Reservation $reservation, Table $table, bool $hasActiveOrder): void
    {
        $releasableStatus = match ($reservation->status) {
            ReservationStatus::Confirmed, ReservationStatus::Arrived => TableStatus::Reserved,
            ReservationStatus::Seated => TableStatus::Occupied,
            default => null,
        };
        if ($releasableStatus && $table->status === $releasableStatus && ! $hasActiveOrder) {
            $table->updateStatusWithEvent(
                TableStatus::Available,
                auth()->id(),
                "RESERVATION_RELEASED:$reservation->reservation_number",
            );
        }
    }

    /** {@inheritDoc} */
    public function assign(int|string|Reservation $reservation, array $tableIds): Reservation
    {
        return $this->assignWithMode($reservation, $tableIds, TableAssignmentMode::AssignNow);
    }

    /** {@inheritDoc} */
    public function remove(int|string|Reservation $reservation, int $tableId): Reservation
    {
        $reservationId = $reservation instanceof Reservation ? $reservation->id : $reservation;

        return DB::transaction(function () use ($reservationId, $tableId): Reservation {
            $reservation = Reservation::query()
                ->with(['tables', 'reservationTables.table', 'branch', 'createdBy'])
                ->lockForUpdate()
                ->findOrFail($reservationId);
            $this->assertCanAssign($reservation);
            abort_unless($reservation->tables->contains('id', $tableId), 404);

            $table = Table::query()
                ->withOutGlobalBranchPermission()
                ->withoutGlobalActive()
                ->where('branch_id', $reservation->branch_id)
                ->withExists('activeOrder')
                ->lockForUpdate()
                ->findOrFail($tableId);

            $reservation->tables()->detach($tableId);
            $reservation->update([
                'table_assignment_status' => $reservation->tables()->exists()
                    ? TableAssignmentStatus::PartiallyAssigned
                    : TableAssignmentStatus::Unassigned,
            ]);
            $this->releaseRemovedTable($reservation, $table, $table->active_order_exists);

            return $reservation->fresh(['tables', 'reservationTables.table', 'reservationTables.assignedBy', 'statusLogs', 'branch', 'createdBy']);
        });
    }
}
