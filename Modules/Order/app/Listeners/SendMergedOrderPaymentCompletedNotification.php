<?php

namespace Modules\Order\Listeners;

use Illuminate\Contracts\Queue\ShouldQueueAfterCommit;
use Illuminate\Foundation\Queue\Queueable;
use Modules\Order\Events\OrderMergeBillingPaid;
use Modules\Order\Services\OrderNotification\OrderNotificationServiceInterface;

class SendMergedOrderPaymentCompletedNotification implements ShouldQueueAfterCommit
{
    use Queueable;

    /**
     * Handle the event.
     */
    public function handle(OrderMergeBillingPaid $event): void
    {
        app(OrderNotificationServiceInterface::class)->sendMergedPaymentCompleted($event->tableMerge);
    }
}
