<?php

namespace Modules\Inventory\Listeners;

use Illuminate\Contracts\Queue\ShouldQueueAfterCommit;
use Illuminate\Foundation\Queue\Queueable;
use Modules\Inventory\Events\StockTransferStatusChanged;
use Modules\Inventory\Services\InventoryNotification\InventoryNotificationServiceInterface;

class SendStockTransferStatusChangedNotification implements ShouldQueueAfterCommit
{
    use Queueable;

    /**
     * Send the localized stock-transfer status notification.
     */
    public function handle(StockTransferStatusChanged $event): void
    {
        app(InventoryNotificationServiceInterface::class)
            ->sendStockTransferStatusChanged(
                $event->transfer,
                $event->status,
                $event->reason,
            );
    }
}
