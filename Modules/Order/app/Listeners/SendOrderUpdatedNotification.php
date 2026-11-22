<?php

namespace Modules\Order\Listeners;

use Illuminate\Contracts\Queue\ShouldQueueAfterCommit;
use Illuminate\Foundation\Queue\Queueable;
use Modules\Order\Events\OrderUpdated;
use Modules\Order\Services\OrderNotification\OrderNotificationServiceInterface;

class SendOrderUpdatedNotification implements ShouldQueueAfterCommit
{
    use Queueable;

    /**
     * Handle the event.
     */
    public function handle(OrderUpdated $event): void
    {
        app(OrderNotificationServiceInterface::class)->sendUpdated($event->order);
    }
}
