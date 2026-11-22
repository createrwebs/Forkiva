<?php

namespace Modules\Inventory\Listeners;

use Illuminate\Contracts\Queue\ShouldQueueAfterCommit;
use Illuminate\Foundation\Queue\Queueable;
use Modules\Inventory\Events\PurchaseMarkedAsReceived;
use Modules\Inventory\Services\InventoryNotification\InventoryNotificationServiceInterface;

class SendStockMovementNeedsAttentionNotification implements ShouldQueueAfterCommit
{
    use Queueable;

    /**
     * Handle the event.
     */
    public function handle(PurchaseMarkedAsReceived $event): void
    {
        app(InventoryNotificationServiceInterface::class)->sendStockMovementNeedsAttention($event->purchase);
    }
}
