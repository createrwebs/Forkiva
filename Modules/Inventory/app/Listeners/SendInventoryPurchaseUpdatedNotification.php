<?php

namespace Modules\Inventory\Listeners;

use Illuminate\Contracts\Queue\ShouldQueueAfterCommit;
use Illuminate\Foundation\Queue\Queueable;
use Modules\Inventory\Events\PurchaseUpdated;
use Modules\Inventory\Services\InventoryNotification\InventoryNotificationServiceInterface;

class SendInventoryPurchaseUpdatedNotification implements ShouldQueueAfterCommit
{
    use Queueable;

    /**
     * Handle the event.
     */
    public function handle(PurchaseUpdated $event): void
    {
        app(InventoryNotificationServiceInterface::class)->sendPurchaseUpdated($event->purchase);
    }
}
