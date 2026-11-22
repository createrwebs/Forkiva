<?php

namespace Modules\Reservation\Services\ReservationAnalytics;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Modules\Branch\Services\BranchAccess\BranchAccessServiceInterface;
use Modules\Reservation\Enums\ReservationAnalyticsWidget;
use Modules\Reservation\Enums\ReservationStatus;
use Modules\Reservation\Enums\ReservationType;
use Modules\Reservation\Enums\TableAssignmentStatus;
use Modules\Reservation\Models\Reservation;
use Modules\SeatingPlan\Models\Table;
use Modules\Support\DateRange;
use Modules\Support\Enums\DateTimeFormat;
use Modules\Support\GlobalStructureFilters;

class ReservationAnalyticsService implements ReservationAnalyticsServiceInterface
{
    /** @var array<string, mixed> */
    private array $requestMemo = [];

    /**
     * Create a new reservation analytics service instance.
     */
    public function __construct(protected BranchAccessServiceInterface $branchAccessService) {}

    /** {@inheritDoc} */
    public function filters(array $filters = []): array
    {
        $filters = $this->effectiveFilters($filters);
        $branchFilter = GlobalStructureFilters::branch();
        $today = CarbonImmutable::today();

        $defaults = [
            'branch_id' => $filters['branch_id'],
            'from' => $filters['from'],
            'to' => $filters['to'],
        ];

        return [
            'filters' => [
                ...(is_null($branchFilter) ? [] : [$branchFilter]),
                GlobalStructureFilters::from(),
                GlobalStructureFilters::to(),
            ],
            'defaults' => $defaults,
            'default_filters' => $defaults,
            'presets' => [
                [
                    'id' => 'today',
                    'name' => __('reservation::frontend.analytics.periods.today'),
                    'from' => $today->toDateString(),
                    'to' => $today->toDateString(),
                ],
                [
                    'id' => 'last_7_days',
                    'name' => __('reservation::frontend.analytics.periods.last_7_days'),
                    'from' => $today->subDays(6)->toDateString(),
                    'to' => $today->toDateString(),
                ],
                [
                    'id' => 'this_month',
                    'name' => __('reservation::frontend.analytics.periods.this_month'),
                    'from' => $today->startOfMonth()->toDateString(),
                    'to' => $today->toDateString(),
                ],
                [
                    'id' => 'next_30_days',
                    'name' => __('reservation::frontend.analytics.periods.next_30_days'),
                    'from' => $today->toDateString(),
                    'to' => $today->addDays(29)->toDateString(),
                ],
            ],
            'meta' => [
                'branch_locked' => $this->branchAccessService->assignedBranchId() !== null,
                'timezone' => (string) config('app.timezone'),
            ],
        ];
    }

    /**
     * Resolve analytics filters using defaults and the authenticated user's branch scope.
     *
     * @param  array<string, mixed>  $filters
     * @return array<string, mixed>
     */
    protected function effectiveFilters(array $filters): array
    {
        $branchId = $this->branchAccessService->resolve(
            ! empty($filters['branch_id']) ? (int) $filters['branch_id'] : null
        );

        return [
            ...$filters,
            'branch_id' => $branchId,
            'from' => Carbon::parse($filters['from'] ?? now()->startOfMonth())->toDateString(),
            'to' => Carbon::parse($filters['to'] ?? now()->endOfMonth())->toDateString(),
        ];
    }

    /** {@inheritDoc} */
    public function analytics(array $filters = []): array
    {
        $this->requestMemo = [];
        $filters = $this->effectiveFilters($filters);
        $query = $this->query($filters);
        $widgets = collect($filters['widgets'] ?? ReservationAnalyticsWidget::defaultValues())
            ->map(fn (ReservationAnalyticsWidget|string $widget) => $widget instanceof ReservationAnalyticsWidget
                ? $widget
                : ReservationAnalyticsWidget::from($widget));

        return [
            'meta' => [
                'branch_id' => $filters['branch_id'],
                'branch_locked' => $this->branchAccessService->assignedBranchId() !== null,
                'from' => $filters['from'],
                'to' => $filters['to'],
                'timezone' => (string) config('app.timezone'),
                'generated_at' => dateTimeFormat(now()),
            ],
            'widgets' => $widgets
                ->mapWithKeys(fn (ReservationAnalyticsWidget $widget) => [
                    $widget->value => $this->widget($widget, $filters, $query),
                ])
                ->all(),
        ];
    }

    /**
     * Resolve and securely calculate a requested reservation analytics widget.
     *
     * @param  array<string, mixed>  $filters
     */
    protected function widget(
        ReservationAnalyticsWidget $widget,
        array $filters,
        Builder $query,
    ): array {
        $authorized = auth()->user()?->can($widget->permission()) ?? false;

        return [
            'key' => $widget->value,
            'permission' => $widget->permission(),
            'authorized' => $authorized,
            'data' => $authorized ? $this->widgetData($widget, $filters, $query) : null,
        ];
    }

    /**
     * Calculate one requested widget.
     *
     * @param  array<string, mixed>  $filters
     */
    protected function widgetData(
        ReservationAnalyticsWidget $widget,
        array $filters,
        Builder $query,
    ): array {
        if ($widget->isMetric()) {
            $summary = $this->requestMemo['summary'] ??= $this->summary(clone $query);

            return ['value' => $summary[$widget->value]];
        }

        return match ($widget) {
            ReservationAnalyticsWidget::ReservationTrend => $this->reservationTrend(clone $query),
            ReservationAnalyticsWidget::StatusBreakdown => $this->statusBreakdown(clone $query),
            ReservationAnalyticsWidget::TypeBreakdown => $this->typeBreakdown(clone $query),
            ReservationAnalyticsWidget::PeakHours => $this->peakHours(clone $query),
            ReservationAnalyticsWidget::WeekdayDemand => $this->weekdayDemand(clone $query),
            ReservationAnalyticsWidget::PartySizeDistribution => $this->partySizeDistribution(clone $query),
            ReservationAnalyticsWidget::TableUtilization => $this->tableUtilization(clone $query),
            ReservationAnalyticsWidget::UpcomingReservations => $this->upcomingReservations($filters),
            default => [],
        };
    }

    /**
     * Build analytics base query.
     *
     * @param  array<string, mixed>  $filters
     */
    protected function query(array $filters): Builder
    {
        return Reservation::query()
            ->when($filters['branch_id'] ?? null, fn (Builder $query, $branchId) => $query->where('reservations.branch_id', $branchId))
            ->when($filters['from'] ?? null, fn (Builder $query, $from) => $query->where('reservations.reservation_date', '>=', DateRange::start($from)))
            ->when($filters['to'] ?? null, fn (Builder $query, $to) => $query->where('reservations.reservation_date', '<', DateRange::endExclusive($to)));
    }

    /**
     * Get analytics summary counters.
     *
     * @return array<string, mixed>
     */
    protected function summary(Builder $query): array
    {
        $total = (clone $query)->count();
        $cancelled = (clone $query)->where('reservations.status', ReservationStatus::Cancelled->value)->count();
        $noShow = (clone $query)->where('reservations.status', ReservationStatus::NoShow->value)->count();
        $completed = (clone $query)->where('reservations.status', ReservationStatus::Completed->value)->count();
        $totalGuests = (int) (clone $query)->sum('reservations.guest_count');

        return [
            'total_reservations' => $total,
            'total_guests' => $totalGuests,
            'average_party_size' => $total > 0 ? round($totalGuests / $total, 2) : 0,
            'today_count' => (clone $query)
                ->where('reservations.reservation_date', '>=', DateRange::start(Carbon::today()))
                ->where('reservations.reservation_date', '<', DateRange::endExclusive(Carbon::today()))
                ->count(),
            'assigned_count' => (clone $query)->where('reservations.table_assignment_status', TableAssignmentStatus::Assigned->value)->count(),
            'unassigned_count' => (clone $query)->where('reservations.table_assignment_status', TableAssignmentStatus::Unassigned->value)->count(),
            'cancellation_rate' => $this->rate($cancelled, $total),
            'no_show_rate' => $this->rate($noShow, $total),
            'completion_rate' => $this->rate($completed, $total),
        ];
    }

    /**
     * Build reservation and guest totals over time.
     *
     * @return array<int, array<string, mixed>>
     */
    protected function reservationTrend(Builder $query): array
    {
        return $query
            ->selectRaw('reservations.reservation_date as date, COUNT(*) as reservations_count, COALESCE(SUM(reservations.guest_count), 0) as guests_count')
            ->groupBy('reservations.reservation_date')
            ->orderBy('reservations.reservation_date')
            ->get()
            ->map(fn ($row) => [
                'date' => Carbon::parse($row->date)->toDateString(),
                'label' => Carbon::parse($row->date)->format('M j'),
                'reservations_count' => (int) $row->reservations_count,
                'guests_count' => (int) $row->guests_count,
            ])
            ->all();
    }

    /**
     * Calculate percentage rate.
     */
    protected function rate(int $value, int $total): float
    {
        return $total > 0 ? round(($value / $total) * 100, 2) : 0;
    }

    /**
     * Build status distribution rows.
     *
     * @return array<int, array<string, mixed>>
     */
    protected function statusBreakdown(Builder $query): array
    {
        $rows = $query
            ->selectRaw('reservations.status as status, COUNT(*) as reservations_count, COALESCE(SUM(reservations.guest_count), 0) as guests_count')
            ->groupBy('reservations.status')
            ->get()
            ->keyBy(fn (Reservation $reservation) => $reservation->status instanceof ReservationStatus
                ? $reservation->status->value
                : $reservation->status);

        return collect(ReservationStatus::cases())
            ->map(function (ReservationStatus $status) use ($rows) {
                $row = $rows->get($status->value);

                return [
                    'status' => $status->toTrans(),
                    'reservations_count' => (int) ($row?->reservations_count ?? 0),
                    'guests_count' => (int) ($row?->guests_count ?? 0),
                ];
            })
            ->values()
            ->all();
    }

    /**
     * Build reservation type distribution rows.
     *
     * @return array<int, array<string, mixed>>
     */
    protected function typeBreakdown(Builder $query): array
    {
        $rows = $query
            ->selectRaw('reservations.reservation_type as reservation_type, COUNT(*) as reservations_count, COALESCE(SUM(reservations.guest_count), 0) as guests_count')
            ->groupBy('reservations.reservation_type')
            ->get()
            ->keyBy(fn (Reservation $reservation) => $reservation->reservation_type instanceof ReservationType
                ? $reservation->reservation_type->value
                : $reservation->reservation_type);

        return collect(ReservationType::cases())
            ->map(function (ReservationType $type) use ($rows) {
                $row = $rows->get($type->value);

                return [
                    'type' => [
                        'id' => $type->value,
                        'name' => $type->trans(),
                        'color' => $type->color(),
                    ],
                    'reservations_count' => (int) ($row?->reservations_count ?? 0),
                    'guests_count' => (int) ($row?->guests_count ?? 0),
                ];
            })
            ->values()
            ->all();
    }

    /**
     * Build peak-hour distribution rows.
     *
     * @return array<int, array<string, mixed>>
     */
    protected function peakHours(Builder $query): array
    {
        return $query
            ->whereNotNull('reservations.start_at')
            ->selectRaw('reservations.start_time as start_time, COUNT(*) as reservations_count, COALESCE(SUM(reservations.guest_count), 0) as guests_count')
            ->groupBy('reservations.start_time')
            ->get()
            ->groupBy(fn ($row) => Carbon::parse($row->start_time)->hour)
            ->map(function ($rows, int $hour) {
                return [
                    'hour' => $hour,
                    'hour_label' => Carbon::createFromTime($hour)->format('h:i A'),
                    'reservations_count' => (int) $rows->sum('reservations_count'),
                    'guests_count' => (int) $rows->sum('guests_count'),
                ];
            })
            ->sortBy('hour')
            ->values()
            ->all();
    }

    /**
     * Aggregate reservation demand by weekday without database-specific date functions.
     *
     * @return array<int, array<string, mixed>>
     */
    protected function weekdayDemand(Builder $query): array
    {
        $totals = $query
            ->selectRaw('reservations.reservation_date as date, COUNT(*) as reservations_count, COALESCE(SUM(reservations.guest_count), 0) as guests_count')
            ->groupBy('reservations.reservation_date')
            ->get()
            ->reduce(function (array $days, $row) {
                $day = Carbon::parse($row->date)->isoWeekday();
                $days[$day]['reservations_count'] = ($days[$day]['reservations_count'] ?? 0) + (int) $row->reservations_count;
                $days[$day]['guests_count'] = ($days[$day]['guests_count'] ?? 0) + (int) $row->guests_count;

                return $days;
            }, []);

        return collect(range(1, 7))
            ->map(function (int $day) use ($totals) {
                $name = strtolower(Carbon::now()->startOfWeek()->addDays($day - 1)->englishDayOfWeek);

                return [
                    'day' => $day,
                    'label' => __("reservation::frontend.days.{$name}"),
                    'reservations_count' => (int) ($totals[$day]['reservations_count'] ?? 0),
                    'guests_count' => (int) ($totals[$day]['guests_count'] ?? 0),
                ];
            })
            ->values()
            ->all();
    }

    /**
     * Group reservations into operational party-size bands.
     *
     * @return array<int, array<string, mixed>>
     */
    protected function partySizeDistribution(Builder $query): array
    {
        $bands = [
            'one_two' => ['label' => '1–2', 'min' => 1, 'max' => 2],
            'three_four' => ['label' => '3–4', 'min' => 3, 'max' => 4],
            'five_six' => ['label' => '5–6', 'min' => 5, 'max' => 6],
            'seven_eight' => ['label' => '7–8', 'min' => 7, 'max' => 8],
            'nine_plus' => ['label' => '9+', 'min' => 9, 'max' => null],
        ];
        $sizes = $query
            ->selectRaw('reservations.guest_count as guest_count, COUNT(*) as reservations_count')
            ->groupBy('reservations.guest_count')
            ->get();

        return collect($bands)
            ->map(function (array $band, string $id) use ($sizes) {
                $matching = $sizes->filter(fn ($row) => (int) $row->guest_count >= $band['min']
                    && (is_null($band['max']) || (int) $row->guest_count <= $band['max']));

                return [
                    'id' => $id,
                    'label' => $band['label'],
                    'reservations_count' => (int) $matching->sum('reservations_count'),
                    'guests_count' => (int) $matching->sum(
                        fn ($row) => (int) $row->guest_count * (int) $row->reservations_count
                    ),
                ];
            })
            ->values()
            ->all();
    }

    /**
     * Build top table utilization rows.
     *
     * @return array<int, array<string, mixed>>
     */
    protected function tableUtilization(Builder $query): array
    {
        return $query
            ->join('reservation_tables', 'reservation_tables.reservation_id', '=', 'reservations.id')
            ->join('tables', 'tables.id', '=', 'reservation_tables.table_id')
            ->selectRaw('tables.id as table_id, tables.name as table_name, COUNT(reservation_tables.id) as reservations_count, COALESCE(SUM(reservations.guest_count), 0) as guests_count')
            ->groupBy('tables.id', 'tables.name')
            ->orderByDesc('reservations_count')
            ->limit(8)
            ->get()
            ->map(fn ($row) => [
                'table_id' => (int) $row->table_id,
                'table_name' => $row->table_name,
                'reservations_count' => (int) $row->reservations_count,
                'guests_count' => (int) $row->guests_count,
            ])
            ->all();
    }

    /**
     * Get upcoming reservations for analytics.
     *
     * @param  array<string, mixed>  $filters
     * @return array<int, array<string, mixed>>
     */
    protected function upcomingReservations(array $filters): array
    {
        return $this->query($filters)
            ->with('tables:id,name')
            ->where('reservations.start_at', '>=', now())
            ->whereIn('reservations.status', ReservationStatus::blockingValues())
            ->orderBy('reservations.start_at')
            ->limit(6)
            ->get()
            ->map(fn (Reservation $reservation) => [
                'id' => $reservation->id,
                'reservation_number' => $reservation->reservation_number,
                'customer_name' => $reservation->customer_name,
                'start_at' => dateTimeFormat($reservation->start_at),
                'reservation_date' => dateTimeFormat($reservation->reservation_date, DateTimeFormat::Date),
                'start_time' => dateTimeFormat($reservation->start_time, DateTimeFormat::Time),
                'guest_count' => $reservation->guest_count,
                'status' => $reservation->status?->toTrans(),
                'tables' => $reservation->tables->map(fn (Table $table) => [
                    'id' => $table->id,
                    'name' => $table->name,
                ])->values(),
            ])
            ->all();
    }
}
