<?php

namespace Modules\Inventory\Services\InventoryAnalytics;

interface InventoryAnalyticsServiceInterface
{
    /**
     * Get the requested, permission-aware inventory analytics widgets.
     *
     * @param  array<string, mixed>  $filters
     */
    public function analytics(array $filters = []): array;

    /**
     * Get inventory analytics filter metadata and defaults.
     *
     * @param  array<string, mixed>  $filters
     */
    public function filters(array $filters = []): array;
}
