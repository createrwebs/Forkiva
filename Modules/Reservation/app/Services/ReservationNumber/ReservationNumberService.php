<?php

namespace Modules\Reservation\Services\ReservationNumber;

use Carbon\Carbon;
use Carbon\CarbonInterface;
use Modules\Reservation\Models\Reservation;

class ReservationNumberService implements ReservationNumberServiceInterface
{
    /** {@inheritDoc} */
    public function generate(int $branchId, CarbonInterface|string $date): string
    {
        $day = $date instanceof CarbonInterface
            ? $date->format('Ymd')
            : Carbon::parse($date)->format('Ymd');

        $prefix = "RSV-{$day}-";
        $sequence = Reservation::query()
            ->withOutGlobalBranchPermission()
            ->where('branch_id', $branchId)
            ->where('reservation_number', 'like', "{$prefix}%")
            ->count() + 1;

        do {
            $number = $prefix.str_pad((string) $sequence, 4, '0', STR_PAD_LEFT);
            $sequence++;
        } while (Reservation::query()->withOutGlobalBranchPermission()->where('reservation_number', $number)->exists());

        return $number;
    }
}
