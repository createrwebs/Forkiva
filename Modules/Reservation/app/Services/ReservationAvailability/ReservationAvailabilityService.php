<?php

namespace Modules\Reservation\Services\ReservationAvailability;

use Carbon\Carbon;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Modules\Branch\Models\Branch;
use Modules\Branch\Services\BranchAccess\BranchAccessServiceInterface;
use Modules\Reservation\Enums\TableAssignmentMode;
use Modules\Reservation\Models\Reservation;
use Modules\Reservation\Models\ReservationSetting;
use Modules\Reservation\Models\ReservationTableBlock;
use Modules\Reservation\Services\ReservationSetting\ReservationSettingServiceInterface;
use Modules\SeatingPlan\Enums\TableStatus;
use Modules\SeatingPlan\Models\Table;

class ReservationAvailabilityService implements ReservationAvailabilityServiceInterface
{
    /**
     * Create the availability service with settings and branch access dependencies.
     */
    public function __construct(
        protected ReservationSettingServiceInterface $settings,
        protected BranchAccessServiceInterface $branchAccess,
    ) {}

    /** {@inheritDoc} */
    public function suggestedTables(array $data): array
    {
        $branchId = (int) $data['branch_id'];
        $setting = $this->settingFor($branchId);
        [$startAt, $endAt] = $this->window(
            branchId: $branchId,
            date: $data['reservation_date'],
            time: $data['start_time'],
            durationMinutes: (int) ($data['duration_minutes'] ?? $setting->default_duration_minutes)
        );

        return $this->availableTableCombinations(
            branchId: $branchId,
            guestCount: (int) $data['guest_count'],
            startAt: $startAt,
            endAt: $endAt,
            setting: $setting,
            excludeReservationId: $data['exclude_reservation_id'] ?? null
        );
    }

    /** {@inheritDoc} */
    public function settingFor(int $branchId): ReservationSetting
    {
        $branchId = (int) $this->branchAccess->resolve($branchId, required: true);

        return $this->settings->forBranch($branchId);
    }

    /** {@inheritDoc} */
    public function window(int $branchId, string $date, string $time, int $durationMinutes): array
    {
        $branchId = (int) $this->branchAccess->resolve($branchId, required: true);
        $startAt = Carbon::parse("$date $time", $this->branchTimezone($branchId))
            ->setTimezone(config('app.timezone'));

        return [$startAt, $startAt->copy()->addMinutes($durationMinutes)];
    }

    /**
     * Get branch timezone.
     */
    protected function branchTimezone(int $branchId): string
    {
        return Branch::withoutGlobalActive()->find($branchId)?->timezone ?: config('app.timezone');
    }

    /**
     * Build table combinations that can seat the guest count.
     *
     * @return array<int, array<string, mixed>>
     */
    protected function availableTableCombinations(
        int $branchId,
        int $guestCount,
        CarbonInterface $startAt,
        CarbonInterface $endAt,
        ReservationSetting $setting,
        int|string|null $excludeReservationId = null,
    ): array {
        $busyIds = $this->busyTableIds($branchId, $startAt, $endAt, $setting, $excludeReservationId);
        $tables = $this->baseTableQuery($branchId)
            ->whereNotIn('id', $busyIds)
            ->orderBy('capacity')
            ->get();

        /** @var Table|null $single */
        $single = $tables->first(fn (Table $table) => $table->capacity >= $guestCount);
        if ($single) {
            return [[
                'table_ids' => [$single->id],
                'capacity' => $single->capacity,
                'tables' => $this->tablePayload([$single]),
            ]];
        }

        $selected = [];
        $capacity = 0;
        /** @var Table $table */
        foreach ($tables as $table) {
            $selected[] = $table;
            $capacity += $table->capacity;

            if ($capacity >= $guestCount) {
                return [[
                    'table_ids' => collect($selected)->pluck('id')->all(),
                    'capacity' => $capacity,
                    'tables' => $this->tablePayload($selected),
                ]];
            }
        }

        return [];
    }

    /**
     * Get busy table ids for an availability window.
     *
     * @return array<int, int>
     */
    protected function busyTableIds(
        int $branchId,
        CarbonInterface $startAt,
        CarbonInterface $endAt,
        ReservationSetting $setting,
        int|string|null $excludeReservationId = null,
    ): array {
        $bufferedStart = $startAt->copy()->subMinutes($setting->buffer_minutes);
        $bufferedEnd = $endAt->copy()->addMinutes($setting->buffer_minutes);
        $reservationBusyIds = Reservation::query()
            ->withOutGlobalBranchPermission()
            ->where('branch_id', $branchId)
            ->activeForAvailability($setting->pending_blocks_capacity)
            ->when($excludeReservationId, fn ($query) => $query->where('id', '!=', $excludeReservationId))
            ->where('start_at', '<', $bufferedEnd)
            ->where('end_at', '>', $bufferedStart)
            ->whereHas('tables')
            ->with('tables:id')
            ->get()
            ->flatMap(fn (Reservation $reservation) => $reservation->tables->pluck('id'))
            ->unique()
            ->values()
            ->all();

        $blockBusyIds = ReservationTableBlock::query()
            ->withOutGlobalBranchPermission()
            ->where('branch_id', $branchId)
            ->where('start_at', '<', $bufferedEnd)
            ->where('end_at', '>', $bufferedStart)
            ->pluck('table_id')
            ->all();

        if (in_array(null, $blockBusyIds, true)) {
            return $this->baseTableQuery($branchId)->pluck('id')->map(fn ($id) => (int) $id)->all();
        }

        $blockBusyIds = collect($blockBusyIds)->filter()->unique()->values()->all();

        return array_values(array_unique([...$reservationBusyIds, ...$blockBusyIds]));
    }

    /**
     * Get base table query for active usable tables.
     */
    protected function baseTableQuery(int $branchId): Builder
    {
        return Table::query()
            ->withOutGlobalBranchPermission()
            ->withoutGlobalActive()
            ->where('branch_id', $branchId)
            ->where('is_active', true)
            ->whereNot('status', TableStatus::Merged->value);
    }

    /**
     * Normalize table records for API response.
     *
     * @param  array<int, Table>  $tables
     * @return array<int, array<string, mixed>>
     */
    protected function tablePayload(array $tables): array
    {
        return collect($tables)
            ->map(fn (Table $table) => [
                'id' => $table->id,
                'name' => $table->name,
                'capacity' => $table->capacity,
                'status' => method_exists($table->status, 'toTrans') ? $table->status->toTrans() : $table->status,
            ])
            ->values()
            ->all();
    }

    /** {@inheritDoc} */
    public function suggestedSlots(array $data): array
    {
        $branchId = (int) $data['branch_id'];
        $setting = $this->settingFor($branchId);
        $duration = (int) ($data['duration_minutes'] ?? $setting->default_duration_minutes);
        $date = Carbon::parse($data['reservation_date'])->toDateString();
        $day = strtolower(Carbon::parse($date)->englishDayOfWeek);
        $hours = $setting->working_hours[$day] ?? null;

        if (! is_array($hours) || ! ($hours['enabled'] ?? false)) {
            return [];
        }

        $slots = [];
        $current = Carbon::parse("$date {$hours['open']}", $this->branchTimezone($branchId));
        $close = Carbon::parse("$date {$hours['close']}", $this->branchTimezone($branchId));
        if ($close->lessThanOrEqualTo($current)) {
            $close->addDay();
        }

        while ($current->copy()->addMinutes($duration)->lessThanOrEqualTo($close)) {
            $check = $this->check([
                ...$data,
                'reservation_date' => $date,
                'start_time' => $current->format('H:i'),
                'duration_minutes' => $duration,
            ]);

            $slots[] = [
                'time' => $current->format('H:i'),
                'available' => (bool) $check['available'],
                'warnings' => $check['warnings'],
            ];

            $current->addMinutes($setting->slot_interval_minutes);
        }

        return $slots;
    }

    /** {@inheritDoc} */
    public function check(array $data): array
    {
        $branchId = (int) $data['branch_id'];
        $setting = $this->settingFor($branchId);
        $duration = (int) ($data['duration_minutes'] ?? $setting->default_duration_minutes);
        [$startAt, $endAt] = $this->window($branchId, $data['reservation_date'], $data['start_time'], $duration);
        $warnings = [];

        if (! $setting->reservations_enabled) {
            $warnings[] = __('reservation::messages.reservations_disabled');
        }

        if (! $this->insideAdvanceWindow($setting, $startAt)) {
            $warnings[] = __('reservation::messages.outside_advance_window');
        }

        if (! $this->insideWorkingHours($setting, $startAt, $endAt)) {
            $warnings[] = __('reservation::messages.outside_working_hours');
        }

        if ($duration < $setting->min_duration_minutes || $duration > $setting->max_duration_minutes) {
            $warnings[] = __('reservation::messages.duration_out_of_range');
        }

        $guestCount = (int) $data['guest_count'];
        if ($guestCount < $setting->min_guests || $guestCount > $setting->max_guests) {
            $warnings[] = __('reservation::messages.guest_count_out_of_range');
        }

        $tableIds = array_values(array_filter($data['table_ids'] ?? []));
        $assignmentMode = $this->assignmentMode($data);
        $tableResult = match (true) {
            $assignmentMode === TableAssignmentMode::AssignLater && empty($tableIds) => [
                'warnings' => [],
                'suggested_tables' => [],
            ],
            empty($tableIds) => $this->branchCapacityResult($branchId, $guestCount, $startAt, $endAt, $setting, $data),
            default => $this->selectedTablesResult($branchId, $tableIds, $guestCount, $startAt, $endAt, $setting, $data),
        };

        $warnings = array_values(array_unique([...$warnings, ...$tableResult['warnings']]));

        return [
            'available' => empty($warnings),
            'warnings' => $warnings,
            'start_at' => $startAt->toDateTimeString(),
            'end_at' => $endAt->toDateTimeString(),
            'duration_minutes' => $duration,
            'suggested_tables' => $tableResult['suggested_tables'],
        ];
    }

    /**
     * Resolve assignment mode from availability input.
     *
     * @param  array<string, mixed>  $data
     */
    protected function assignmentMode(array $data): ?TableAssignmentMode
    {
        if (empty($data['table_assignment_mode'])) {
            return null;
        }

        return $data['table_assignment_mode'] instanceof TableAssignmentMode
            ? $data['table_assignment_mode']
            : TableAssignmentMode::from($data['table_assignment_mode']);
    }

    /**
     * Determine if start is inside reservation advance window.
     */
    protected function insideAdvanceWindow(ReservationSetting $setting, CarbonInterface $startAt): bool
    {
        $now = now();

        return $startAt->greaterThanOrEqualTo($now->copy()->addMinutes($setting->min_advance_minutes))
            && $startAt->lessThanOrEqualTo($now->copy()->addDays($setting->max_advance_days));
    }

    /**
     * Determine if window is inside configured branch working hours.
     */
    protected function insideWorkingHours(ReservationSetting $setting, CarbonInterface $startAt, CarbonInterface $endAt): bool
    {
        $timezone = $setting->branch?->timezone ?: $this->branchTimezone($setting->branch_id);
        $localStart = $startAt->copy()->setTimezone($timezone);
        $localEnd = $endAt->copy()->setTimezone($timezone);

        foreach ([0, -1] as $dayOffset) {
            $serviceDate = $localStart->copy()->addDays($dayOffset)->startOfDay();
            $day = strtolower($serviceDate->englishDayOfWeek);
            $hours = $setting->working_hours[$day] ?? null;

            if (! is_array($hours) || ! ($hours['enabled'] ?? false)) {
                continue;
            }

            $open = Carbon::parse($serviceDate->toDateString().' '.($hours['open'] ?? '00:00'), $timezone);
            $close = Carbon::parse($serviceDate->toDateString().' '.($hours['close'] ?? '23:59'), $timezone);
            if ($close->lessThanOrEqualTo($open)) {
                $close->addDay();
            }

            if ($localStart->greaterThanOrEqualTo($open) && $localEnd->lessThanOrEqualTo($close)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Check branch capacity when no table is selected.
     *
     * @param  array<string, mixed>  $data
     * @return array{warnings: array<int, string>, suggested_tables: array<int, array<string, mixed>>}
     */
    protected function branchCapacityResult(
        int $branchId,
        int $guestCount,
        CarbonInterface $startAt,
        CarbonInterface $endAt,
        ReservationSetting $setting,
        array $data,
    ): array {
        $suggestedTables = $this->availableTableCombinations(
            branchId: $branchId,
            guestCount: $guestCount,
            startAt: $startAt,
            endAt: $endAt,
            setting: $setting,
            excludeReservationId: $data['exclude_reservation_id'] ?? null
        );

        return [
            'warnings' => empty($suggestedTables) ? [__('reservation::messages.no_available_tables')] : [],
            'suggested_tables' => $suggestedTables,
        ];
    }

    /**
     * Check selected table availability.
     *
     * @param  array<int, int>  $tableIds
     * @param  array<string, mixed>  $data
     * @return array{warnings: array<int, string>, suggested_tables: array<int, array<string, mixed>>}
     */
    protected function selectedTablesResult(
        int $branchId,
        array $tableIds,
        int $guestCount,
        CarbonInterface $startAt,
        CarbonInterface $endAt,
        ReservationSetting $setting,
        array $data,
    ): array {
        $warnings = [];
        $tables = $this->baseTableQuery($branchId)->whereIn('id', $tableIds)->get();

        if ($tables->count() !== count($tableIds)) {
            $warnings[] = __('reservation::messages.selected_tables_invalid');
        }

        if ($tables->sum('capacity') < $guestCount) {
            $warnings[] = __('reservation::messages.selected_tables_capacity_insufficient');
        }

        $busyIds = $this->busyTableIds($branchId, $startAt, $endAt, $setting, $data['exclude_reservation_id'] ?? null);
        if (array_intersect($tableIds, $busyIds) !== []) {
            $warnings[] = __('reservation::messages.selected_tables_unavailable');
        }

        return [
            'warnings' => $warnings,
            'suggested_tables' => $this->tablePayload($tables->all()),
        ];
    }
}
