<?php

namespace Modules\Order\Listeners;

use Modules\Order\Events\OrderUpdateStatus;
use Modules\Order\Services\OrderNotification\OrderNotificationServiceInterface;

class SendOrderStatusChangedNotification
{
    /**
     * Handle the event.
     */
    public function handle(OrderUpdateStatus $event): void
    {
        app(OrderNotificationServiceInterface::class)->sendStatusChanged($event);
    }
}
