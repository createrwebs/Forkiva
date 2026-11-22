<?php

namespace Modules\Reservation\Services\ReservationAvailability;

use Carbon\CarbonInterface;
use Modules\Reservation\Models\ReservationSetting;

interface ReservationAvailabilityServiceInterface
{
    /**
     * Check availability for reservation input.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public function check(array $data): array;

    /**
     * Get suggested tables for reservation input.
     *
     * @param  array<string, mixed>  $data
     * @return array<int, array<string, mixed>>
     */
    public function suggestedTables(array $data): array;

    /**
     * Get suggested time slots for a branch/date.
     *
     * @param  array<string, mixed>  $data
     * @return array<int, array<string, mixed>>
     */
    public function suggestedSlots(array $data): array;

    /**
     * Normalize reservation local date and time to app-timezone window.
     *
     * @return array{0: CarbonInterface, 1: CarbonInterface}
     */
    public function window(int $branchId, string $date, string $time, int $durationMinutes): array;

    /**
     * Get branch setting.
     */
    public function settingFor(int $branchId): ReservationSetting;
}
