<?php

namespace Modules\Order\Listeners;

use DB;
use Modules\Order\Enums\OrderProductStatus;
use Modules\Order\Enums\OrderStatus;
use Modules\Order\Events\OrderUpdateStatus;

class UpdateOrderProductStatus
{
    /**
     * Handle the event.
     */
    public function handle(OrderUpdateStatus $event): void
    {
        $this->recordOrderKitchenTiming($event);

        if ($event->stopUpdateOrderProductStatus) {
            return;
        }

        $query = $event->order->products()
            ->whereNotIn('status', [OrderProductStatus::Cancelled, OrderProductStatus::Refunded]);

        switch ($event->status->value) {
            case OrderStatus::Preparing->value:
                $query
                    ->where('status', OrderProductStatus::Pending)
                    ->update([
                        'status' => $event->status,
                        'preparation_started_at' => DB::raw('COALESCE(preparation_started_at, CURRENT_TIMESTAMP)'),
                    ]);
                break;
            case OrderStatus::Ready->value:
                $query
                    ->where('status', OrderProductStatus::Preparing)
                    ->update([
                        'status' => $event->status,
                        'prepared_at' => DB::raw('COALESCE(prepared_at, CURRENT_TIMESTAMP)'),
                    ]);
                break;
            case OrderStatus::Served->value:
                $query
                    ->where('status', OrderProductStatus::Ready)
                    ->update(['status' => $event->status]);
                break;
            case OrderStatus::Completed->value:
                $query
                    ->whereNotIn('status', [OrderProductStatus::Cancelled, OrderProductStatus::Refunded])
                    ->update(['status' => OrderProductStatus::Served]);
                break;
            case OrderStatus::Cancelled->value:
            case OrderStatus::Refunded->value:
                $event->order->products()
                    ->whereNotIn('status', [OrderProductStatus::Cancelled, OrderProductStatus::Refunded])
                    ->update(['status' => $event->status->value]);
                break;
        }
    }

    /**
     * Record order-level kitchen events without storing calculated timer values.
     */
    private function recordOrderKitchenTiming(OrderUpdateStatus $event): void
    {
        if ($event->status === OrderStatus::Preparing) {
            $event->order->newQuery()
                ->whereKey($event->order->id)
                ->update([
                    'preparation_started_at' => DB::raw('COALESCE(preparation_started_at, CURRENT_TIMESTAMP)'),
                    'prepared_at' => null,
                ]);

            $event->order->setAttribute(
                'preparation_started_at',
                $event->order->newQuery()->whereKey($event->order->id)->value('preparation_started_at')
            );
            $event->order->setAttribute('prepared_at', null);
        }

        if ($event->status === OrderStatus::Ready && is_null($event->order->prepared_at)) {
            $preparedAt = now();

            $event->order->newQuery()
                ->whereKey($event->order->id)
                ->whereNull('prepared_at')
                ->update(['prepared_at' => $preparedAt]);

            $event->order->setAttribute('prepared_at', $preparedAt);
        }
    }
}
