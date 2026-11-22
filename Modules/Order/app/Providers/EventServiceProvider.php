<?php

namespace Modules\Order\Providers;

use Illuminate\Foundation\Support\Providers\EventServiceProvider as ServiceProvider;
use Modules\Order\Events\OrderCreated;
use Modules\Order\Events\OrderMergeBillingPaid;
use Modules\Order\Events\OrderPaid;
use Modules\Order\Events\OrderUpdated;
use Modules\Order\Events\OrderUpdateStatus;
use Modules\Order\Events\OrderVoided;
use Modules\Order\Listeners\CreateOrderCreditNote;
use Modules\Order\Listeners\CreateOrderPaidInvoice;
use Modules\Order\Listeners\DeductOrderStock;
use Modules\Order\Listeners\HandleOrderLoyaltyPoints;
use Modules\Order\Listeners\MarkTableAsFree;
use Modules\Order\Listeners\OrderRefundAmount;
use Modules\Order\Listeners\RestoreOrderStock;
use Modules\Order\Listeners\SendKitchenItemReadyNotification;
use Modules\Order\Listeners\SendMergedOrderPaymentCompletedNotification;
use Modules\Order\Listeners\SendOrderCreatedNotification;
use Modules\Order\Listeners\SendOrderPaymentCompletedNotification;
use Modules\Order\Listeners\SendOrderStatusChangedNotification;
use Modules\Order\Listeners\SendOrderUpdatedNotification;
use Modules\Order\Listeners\SendOrderVoidedNotification;
use Modules\Order\Listeners\SnapshotOrderStock;
use Modules\Order\Listeners\StoreOrderStatusLogo;
use Modules\Order\Listeners\UpdateOrderProductStatus;

class EventServiceProvider extends ServiceProvider
{
    /**
     * The event listener mappings for the application.
     *
     * @var array
     */
    protected $listen = [
        OrderUpdateStatus::class => [
            SnapshotOrderStock::class,
            StoreOrderStatusLogo::class,
            MarkTableAsFree::class,
            DeductOrderStock::class,
            UpdateOrderProductStatus::class,
            HandleOrderLoyaltyPoints::class,
            SendKitchenItemReadyNotification::class,
            SendOrderStatusChangedNotification::class,
        ],
        OrderVoided::class => [
            OrderRefundAmount::class,
            RestoreOrderStock::class,
            CreateOrderCreditNote::class,
            SendOrderVoidedNotification::class,
        ],
        OrderCreated::class => [
            SnapshotOrderStock::class,
            SendOrderCreatedNotification::class,
        ],
        OrderUpdated::class => [
            SnapshotOrderStock::class,
            SendOrderUpdatedNotification::class,
        ],
        OrderPaid::class => [
            CreateOrderPaidInvoice::class,
            SendOrderPaymentCompletedNotification::class,
        ],
        OrderMergeBillingPaid::class => [
            CreateOrderPaidInvoice::class,
            SendMergedOrderPaymentCompletedNotification::class,
        ],
    ];
}
