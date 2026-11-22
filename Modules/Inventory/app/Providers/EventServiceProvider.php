<?php

namespace Modules\Inventory\Providers;

use Illuminate\Foundation\Support\Providers\EventServiceProvider as ServiceProvider;
use Modules\Inventory\Events\PurchaseMarkedAsReceived;
use Modules\Inventory\Events\PurchaseUpdated;
use Modules\Inventory\Events\StockTransferStatusChanged;
use Modules\Inventory\Listeners\SendInventoryPurchaseUpdatedNotification;
use Modules\Inventory\Listeners\SendStockMovementNeedsAttentionNotification;
use Modules\Inventory\Listeners\SendStockTransferStatusChangedNotification;

class EventServiceProvider extends ServiceProvider
{
    /**
     * The event listener mappings for the application.
     *
     * @var array
     */
    protected $listen = [
        PurchaseUpdated::class => [
            SendInventoryPurchaseUpdatedNotification::class,
        ],
        PurchaseMarkedAsReceived::class => [
            SendStockMovementNeedsAttentionNotification::class,
        ],
        StockTransferStatusChanged::class => [
            SendStockTransferStatusChangedNotification::class,
        ],
    ];
}
