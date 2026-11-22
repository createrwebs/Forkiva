<?php

namespace Modules\Order\Listeners;

use Illuminate\Contracts\Queue\ShouldQueueAfterCommit;
use Illuminate\Foundation\Queue\Queueable;
use Modules\Order\Events\OrderUpdateStatus;
use Modules\Order\Services\OrderNotification\OrderNotificationServiceInterface;

class SendKitchenItemReadyNotification implements ShouldQueueAfterCommit
{
    use Queueable;

    /**
     * Handle the event.
     */
    public function handle(OrderUpdateStatus $event): void
    {
        app(OrderNotificationServiceInterface::class)->sendKitchenItemReady($event);
    }
}
