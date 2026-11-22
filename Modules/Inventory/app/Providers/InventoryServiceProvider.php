<?php

namespace Modules\Inventory\Providers;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\ServiceProvider;
use Modules\Inventory\Console\ProcessInventoryStockSyncJobsCommand;
use Modules\Inventory\Console\ReconcileInventoryStockCommand;
use Modules\Inventory\Console\RemediateLegacyStockCostsCommand;
use Modules\Inventory\Models\StockTransferItem;
use Modules\Inventory\Models\StockTransferReceipt;
use Modules\Inventory\Models\StockTransferReceiptItem;
use Modules\Inventory\Observers\StockTransferItemObserver;
use Modules\Inventory\Observers\StockTransferReceiptItemObserver;
use Modules\Inventory\Observers\StockTransferReceiptObserver;

class InventoryServiceProvider extends ServiceProvider
{
    /**
     * Bootstrap module services.
     */
    public function boot(): void
    {
        StockTransferItem::observe(StockTransferItemObserver::class);
        StockTransferReceipt::observe(StockTransferReceiptObserver::class);
        StockTransferReceiptItem::observe(StockTransferReceiptItemObserver::class);

        if ($this->app->runningInConsole()) {
            $this->commands([
                ProcessInventoryStockSyncJobsCommand::class,
                ReconcileInventoryStockCommand::class,
                RemediateLegacyStockCostsCommand::class,
            ]);
        }

        $this->app->booted(function ($app): void {
            $app[Schedule::class]
                ->command(ProcessInventoryStockSyncJobsCommand::class)
                ->everyMinute()
                ->onOneServer()
                ->withoutOverlapping(10);
        });
    }
}
