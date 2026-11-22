<?php

namespace Modules\Inventory\Services\StockSyncJob;

use Modules\Inventory\Models\InventoryStockSyncJob;
use Modules\Order\Models\Order;
use Throwable;

interface StockSyncJobServiceInterface
{
    /**
     * Persist and dispatch one idempotent stock deduction operation.
     *
     * @throws Throwable
     */
    public function queueDeduction(Order $order): InventoryStockSyncJob;

    /**
     * Persist and dispatch one idempotent stock restoration operation.
     *
     * @throws Throwable
     */
    public function queueRestoration(Order $order): InventoryStockSyncJob;

    /**
     * Dispatch pending operations whose original queue dispatch is missing or stale.
     *
     * @throws Throwable
     */
    public function dispatchDue(int $limit = 100): int;

    /**
     * Process one persisted stock synchronization operation.
     *
     * @throws Throwable
     */
    public function process(int|InventoryStockSyncJob $job, ?string $dispatchToken = null): InventoryStockSyncJob;
}
