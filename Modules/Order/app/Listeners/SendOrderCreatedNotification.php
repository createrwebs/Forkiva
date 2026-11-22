<?php

namespace Modules\Order\Listeners;

use Illuminate\Contracts\Queue\ShouldQueueAfterCommit;
use Illuminate\Foundation\Queue\Queueable;
use Modules\Order\Events\OrderCreated;
use Modules\Order\Services\OrderNotification\OrderNotificationServiceInterface;

class SendOrderCreatedNotification implements ShouldQueueAfterCommit
{
    use Queueable;

    /**
     * Handle the event.
     */
    public function handle(OrderCreated $event): void
    {
        app(OrderNotificationServiceInterface::class)->sendCreated($event->order);
    }
}
