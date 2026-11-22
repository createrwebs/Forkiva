<?php

namespace Modules\Reservation\Services\ReservationTableAssignment;

use Modules\Reservation\Models\Reservation;
use Throwable;

interface ReservationTableAssignmentServiceInterface
{
    /**
     * Assign tables manually.
     *
     * @param  array<int, int>  $tableIds
     *
     * @throws Throwable
     */
    public function assign(int|string|Reservation $reservation, array $tableIds): Reservation;

    /**
     * Auto assign tables.
     */
    public function autoAssign(int|string|Reservation $reservation): Reservation;

    /**
     * Remove one table assignment.
     *
     * @throws Throwable
     */
    public function remove(int|string|Reservation $reservation, int $tableId): Reservation;
}
