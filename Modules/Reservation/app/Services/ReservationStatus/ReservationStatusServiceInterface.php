<?php

namespace Modules\Reservation\Services\ReservationStatus;

use Modules\Reservation\Enums\ReservationStatus;
use Modules\Reservation\Models\Reservation;
use Throwable;

interface ReservationStatusServiceInterface
{
    /**
     * Move reservation to a target status.
     *
     * @param  array<string, mixed>  $data
     *
     * @throws Throwable
     */
    public function transition(int|string|Reservation $reservation, ReservationStatus $status, array $data = []): Reservation;
}
