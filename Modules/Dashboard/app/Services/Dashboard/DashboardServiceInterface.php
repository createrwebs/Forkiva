<?php

namespace Modules\Dashboard\Services\Dashboard;

interface DashboardServiceInterface
{
    /**
     * Get filter metadata and defaults for the main dashboard.
     *
     * @param  array<string, mixed>  $filters
     */
    public function filters(array $filters = []): array;

    /**
     * Get the requested, permission-aware dashboard widgets.
     *
     * @param  array<string, mixed>  $filters
     */
    public function dashboard(array $filters = []): array;
}
