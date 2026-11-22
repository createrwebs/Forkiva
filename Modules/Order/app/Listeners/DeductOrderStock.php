<?php

namespace Modules\Order\Listeners;

use Illuminate\Contracts\Queue\ShouldQueueAfterCommit;
use Illuminate\Foundation\Queue\Queueable;
use Modules\Inventory\Services\StockSyncJob\StockSyncJobServiceInterface;
use Modules\Order\Enums\OrderStatus;
use Modules\Order\Events\OrderUpdateStatus;
use Throwable;

class DeductOrderStock implements ShouldQueueAfterCommit
{
    use Queueable;

    /**
     * Handle the event.
     *
     * @throws Throwable
     */
    public function handle(OrderUpdateStatus $event): void
    {
        if ($event->status === OrderStatus::Completed && ! $event->order->is_stock_deducted) {
            app(StockSyncJobServiceInterface::class)->queueDeduction($event->order);
        }
    }
}
