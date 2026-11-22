<?php

namespace Modules\Inventory\Providers;

use Illuminate\Contracts\Support\DeferrableProvider;
use Illuminate\Support\ServiceProvider;
use Modules\Inventory\Services\Ingredient\IngredientService;
use Modules\Inventory\Services\Ingredient\IngredientServiceInterface;
use Modules\Inventory\Services\InventoryAnalytics\InventoryAnalyticsService;
use Modules\Inventory\Services\InventoryAnalytics\InventoryAnalyticsServiceInterface;
use Modules\Inventory\Services\InventoryCount\InventoryCountService;
use Modules\Inventory\Services\InventoryCount\InventoryCountServiceInterface;
use Modules\Inventory\Services\InventoryNotification\InventoryNotificationService;
use Modules\Inventory\Services\InventoryNotification\InventoryNotificationServiceInterface;
use Modules\Inventory\Services\Purchase\PurchaseService;
use Modules\Inventory\Services\Purchase\PurchaseServiceInterface;
use Modules\Inventory\Services\StockCostResolver\StockCostResolverService;
use Modules\Inventory\Services\StockCostResolver\StockCostResolverServiceInterface;
use Modules\Inventory\Services\StockMovement\StockMovementService;
use Modules\Inventory\Services\StockMovement\StockMovementServiceInterface;
use Modules\Inventory\Services\StockReconciliation\StockReconciliationService;
use Modules\Inventory\Services\StockReconciliation\StockReconciliationServiceInterface;
use Modules\Inventory\Services\StockSync\StockSyncService;
use Modules\Inventory\Services\StockSync\StockSyncServiceInterface;
use Modules\Inventory\Services\StockSyncJob\StockSyncJobService;
use Modules\Inventory\Services\StockSyncJob\StockSyncJobServiceInterface;
use Modules\Inventory\Services\StockTransfer\StockTransferService;
use Modules\Inventory\Services\StockTransfer\StockTransferServiceInterface;
use Modules\Inventory\Services\Supplier\SupplierService;
use Modules\Inventory\Services\Supplier\SupplierServiceInterface;
use Modules\Inventory\Services\SupplierReturnMovement\SupplierReturnMovementService;
use Modules\Inventory\Services\SupplierReturnMovement\SupplierReturnMovementServiceInterface;
use Modules\Inventory\Services\Unit\UnitService;
use Modules\Inventory\Services\Unit\UnitServiceInterface;

class DeferredInventoryServiceProvider extends ServiceProvider implements DeferrableProvider
{
    /**
     * Boot the application events.
     */
    public function register(): void
    {
        $this->app->singleton(
            abstract: IngredientServiceInterface::class,
            concrete: fn ($app) => $app->make(IngredientService::class)
        );

        $this->app->singleton(
            abstract: SupplierServiceInterface::class,
            concrete: fn ($app) => $app->make(SupplierService::class)
        );

        $this->app->singleton(
            abstract: UnitServiceInterface::class,
            concrete: fn ($app) => $app->make(UnitService::class)
        );

        $this->app->singleton(
            abstract: StockCostResolverServiceInterface::class,
            concrete: fn ($app) => $app->make(StockCostResolverService::class)
        );

        $this->app->singleton(
            abstract: StockMovementServiceInterface::class,
            concrete: fn ($app) => $app->make(StockMovementService::class)
        );

        $this->app->singleton(
            abstract: StockReconciliationServiceInterface::class,
            concrete: fn ($app) => $app->make(StockReconciliationService::class)
        );

        $this->app->singleton(
            abstract: PurchaseServiceInterface::class,
            concrete: fn ($app) => $app->make(PurchaseService::class)
        );

        $this->app->singleton(
            abstract: InventoryAnalyticsServiceInterface::class,
            concrete: fn ($app) => $app->make(InventoryAnalyticsService::class)
        );

        $this->app->singleton(
            abstract: StockSyncServiceInterface::class,
            concrete: fn ($app) => $app->make(StockSyncService::class)
        );

        $this->app->singleton(
            abstract: StockSyncJobServiceInterface::class,
            concrete: fn ($app) => $app->make(StockSyncJobService::class)
        );

        $this->app->singleton(
            abstract: SupplierReturnMovementServiceInterface::class,
            concrete: fn ($app) => $app->make(SupplierReturnMovementService::class)
        );

        $this->app->singleton(
            abstract: InventoryNotificationServiceInterface::class,
            concrete: fn ($app) => $app->make(InventoryNotificationService::class)
        );

        $this->app->singleton(
            abstract: InventoryCountServiceInterface::class,
            concrete: fn ($app) => $app->make(InventoryCountService::class)
        );

        $this->app->singleton(
            abstract: StockTransferServiceInterface::class,
            concrete: fn ($app) => $app->make(StockTransferService::class)
        );
    }

    /**
     * Get the services provided by the provider.
     */
    public function provides(): array
    {
        return [
            SupplierServiceInterface::class,
            IngredientServiceInterface::class,
            UnitServiceInterface::class,
            StockMovementServiceInterface::class,
            StockCostResolverServiceInterface::class,
            StockReconciliationServiceInterface::class,
            PurchaseServiceInterface::class,
            InventoryAnalyticsServiceInterface::class,
            StockSyncServiceInterface::class,
            StockSyncJobServiceInterface::class,
            SupplierReturnMovementServiceInterface::class,
            InventoryNotificationServiceInterface::class,
            InventoryCountServiceInterface::class,
            StockTransferServiceInterface::class,
        ];
    }
}
