<?php

namespace Modules\Order\Listeners;

use Illuminate\Contracts\Queue\ShouldQueueAfterCommit;
use Illuminate\Foundation\Queue\Queueable;
use Modules\Order\Events\OrderVoided;
use Modules\Order\Services\OrderNotification\OrderNotificationServiceInterface;

class SendOrderVoidedNotification implements ShouldQueueAfterCommit
{
    use Queueable;

    /**
     * Handle the event.
     */
    public function handle(OrderVoided $event): void
    {
        app(OrderNotificationServiceInterface::class)->sendVoided($event);
    }
}
