<?php

namespace Modules\Order\Listeners;

use Modules\Inventory\Services\StockSync\StockSyncServiceInterface;
use Modules\Order\Enums\OrderStatus;
use Modules\Order\Events\OrderCreated;
use Modules\Order\Events\OrderUpdated;
use Modules\Order\Events\OrderUpdateStatus;
use Throwable;

class SnapshotOrderStock
{
    /**
     * Capture a new or deliberately edited order BOM without following later catalog edits.
     *
     * @throws Throwable
     */
    public function handle(OrderCreated|OrderUpdated|OrderUpdateStatus $event): void
    {
        if ($event instanceof OrderUpdateStatus
            && in_array($event->status, [
                OrderStatus::Cancelled,
                OrderStatus::Refunded,
                OrderStatus::Merged,
            ], true)) {
            return;
        }

        if ($event instanceof OrderUpdateStatus
            && $event->status === OrderStatus::Completed
            && ! $event->order->stock_effective_at) {
            $event->order->forceFill(['stock_effective_at' => now()])->save();
        }

        app(StockSyncServiceInterface::class)->snapshotOrderStock(
            $event->order,
            $event instanceof OrderCreated || $event instanceof OrderUpdated,
        );
    }
}
