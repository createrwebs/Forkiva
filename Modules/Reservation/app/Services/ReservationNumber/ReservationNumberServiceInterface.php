<?php

namespace Modules\Reservation\Services\ReservationNumber;

use Carbon\CarbonInterface;

interface ReservationNumberServiceInterface
{
    /**
     * Generate a unique reservation number.
     */
    public function generate(int $branchId, CarbonInterface|string $date): string;
}
