<?php

namespace Modules\Reservation\Services\ReservationAnalytics;

interface ReservationAnalyticsServiceInterface
{
    /**
     * Get reservation analytics filters and defaults.
     *
     * @param  array<string, mixed>  $filters
     * @return array<string, mixed>
     */
    public function filters(array $filters = []): array;

    /**
     * Get the requested, permission-aware reservation analytics widgets.
     *
     * @param  array<string, mixed>  $filters
     * @return array<string, mixed>
     */
    public function analytics(array $filters = []): array;
}
