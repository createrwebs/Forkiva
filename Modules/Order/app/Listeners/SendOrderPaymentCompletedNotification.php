<?php

namespace Modules\Order\Listeners;

use Illuminate\Contracts\Queue\ShouldQueueAfterCommit;
use Illuminate\Foundation\Queue\Queueable;
use Modules\Order\Events\OrderPaid;
use Modules\Order\Services\OrderNotification\OrderNotificationServiceInterface;

class SendOrderPaymentCompletedNotification implements ShouldQueueAfterCommit
{
    use Queueable;

    /**
     * Handle the event.
     */
    public function handle(OrderPaid $event): void
    {
        app(OrderNotificationServiceInterface::class)->sendPaymentCompleted($event->order);
    }
}
