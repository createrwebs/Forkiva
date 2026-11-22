<?php

namespace Modules\Order\Listeners;

use Illuminate\Contracts\Queue\ShouldQueueAfterCommit;
use Illuminate\Foundation\Queue\Queueable;
use Modules\Inventory\Services\StockSyncJob\StockSyncJobServiceInterface;
use Modules\Order\Enums\OrderStatus;
use Modules\Order\Events\OrderVoided;
use Throwable;

class RestoreOrderStock implements ShouldQueueAfterCommit
{
    use Queueable;

    /**
     * Handle the event.
     *
     * @throws Throwable
     */
    public function handle(OrderVoided $event): void
    {
        if (! in_array($event->status, [OrderStatus::Cancelled, OrderStatus::Refunded], true)) {
            return;
        }

        app(StockSyncJobServiceInterface::class)->queueRestoration($event->order);
    }
}
