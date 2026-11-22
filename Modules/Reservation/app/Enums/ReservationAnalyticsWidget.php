<?php

namespace Modules\Reservation\Enums;

use Modules\Support\Traits\EnumArrayable;

enum ReservationAnalyticsWidget: string
{
    use EnumArrayable;

    case TotalReservations = 'total_reservations';
    case TotalGuests = 'total_guests';
    case AveragePartySize = 'average_party_size';
    case TodayCount = 'today_count';
    case AssignedCount = 'assigned_count';
    case UnassignedCount = 'unassigned_count';
    case CancellationRate = 'cancellation_rate';
    case NoShowRate = 'no_show_rate';
    case CompletionRate = 'completion_rate';
    case ReservationTrend = 'reservation_trend';
    case StatusBreakdown = 'status_breakdown';
    case TypeBreakdown = 'type_breakdown';
    case PeakHours = 'peak_hours';
    case WeekdayDemand = 'weekday_demand';
    case PartySizeDistribution = 'party_size_distribution';
    case TableUtilization = 'table_utilization';
    case UpcomingReservations = 'upcoming_reservations';

    /**
     * Get the permission required to calculate this widget.
     */
    public function permission(): string
    {
        return 'admin.reservations.analytics';
    }

    /**
     * Get the widgets used by the default analytics layout.
     *
     * @return array<int, string>
     */
    public static function defaultValues(): array
    {
        return [
            self::TotalReservations->value,
            self::TotalGuests->value,
            self::AveragePartySize->value,
            self::CancellationRate->value,
            self::ReservationTrend->value,
            self::StatusBreakdown->value,
            self::PeakHours->value,
            self::TypeBreakdown->value,
            self::WeekdayDemand->value,
            self::PartySizeDistribution->value,
            self::TableUtilization->value,
            self::UpcomingReservations->value,
        ];
    }

    /**
     * Determine whether this widget is a summary metric.
     */
    public function isMetric(): bool
    {
        return in_array($this, [
            self::TotalReservations,
            self::TotalGuests,
            self::AveragePartySize,
            self::TodayCount,
            self::AssignedCount,
            self::UnassignedCount,
            self::CancellationRate,
            self::NoShowRate,
            self::CompletionRate,
        ], true);
    }
}
