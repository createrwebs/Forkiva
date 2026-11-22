<?php

namespace Modules\Pos\Services\KitchenViewer;

use Modules\Order\Models\Order;
use Throwable;

interface KitchenViewerServiceInterface
{
    /**
     * Get configuration
     */
    public function getConfiguration(?int $branchId = null): array;

    /**
     * Get orders
     *
     * @param  array<string, mixed>  $filters
     */
    public function getOrders(?int $branchId = null, ?string $lastUpdatedAt = null, array $filters = []): array;

    /**
     * Move order products to next status
     *
     * @throws Throwable
     */
    public function moveOrderProductToNextStatus(int|string $orderId, array|int $ids): Order;
}
