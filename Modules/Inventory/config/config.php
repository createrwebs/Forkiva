<?php

use Modules\Inventory\Imports\IngredientImportDefinition;
use Modules\Inventory\Imports\SupplierImportDefinition;
use Modules\Inventory\Imports\UnitImportDefinition;
use Modules\Inventory\Services\Ingredient\IngredientServiceInterface;
use Modules\Inventory\Services\InventoryCount\InventoryCountServiceInterface;
use Modules\Inventory\Services\Purchase\PurchaseServiceInterface;
use Modules\Inventory\Services\StockMovement\StockMovementServiceInterface;
use Modules\Inventory\Services\StockTransfer\StockTransferServiceInterface;
use Modules\Inventory\Services\Supplier\SupplierServiceInterface;
use Modules\Inventory\Services\Unit\UnitServiceInterface;
use Modules\User\Enums\PermissionAction as Action;

return [
    'stock_sync' => [
        'batch_size' => 100,
        'dispatch_stale_seconds' => 600,
        'processing_lease_seconds' => 600,
        'queue_retry_after' => 900,
        'job_timeout' => 600,
        'attempts' => 5,
        'backoff' => [15, 30, 60, 180, 360],
    ],

    'stock' => [
        'allow_negative' => true,
    ],

    'permissions' => [
        'inventories' => [Action::Analytics],
        'suppliers' => [Action::Index, Action::Show, Action::Create, Action::Edit, Action::Destroy, Action::Export, Action::Import],
        'units' => [Action::Index, Action::Show, Action::Create, Action::Edit, Action::Destroy, Action::Export, Action::Import],
        'ingredients' => [Action::Index, Action::Show, Action::Create, Action::Edit, Action::Destroy, Action::Export, Action::Import],
        'stock_movements' => [Action::Index, Action::Show, Action::Create, Action::Correct, Action::Export],
        'purchases' => [Action::Index, Action::Show, Action::Create, Action::Edit, Action::Destroy, Action::markAsReceived, Action::Export],
        'inventory_counts' => [Action::Index, Action::Show, Action::Create, Action::Edit, Action::Destroy, Action::Approve, Action::Cancel, Action::Export],
        'stock_transfers' => [Action::Index, Action::Show, Action::Create, Action::Edit, Action::Approve, Action::Dispatch, Action::Receive, Action::Close, Action::Cancel, Action::Void, Action::Export],
    ],

    'export_resources' => [
        'suppliers' => SupplierServiceInterface::class,
        'units' => UnitServiceInterface::class,
        'ingredients' => IngredientServiceInterface::class,
        'stock_movements' => StockMovementServiceInterface::class,
        'purchases' => PurchaseServiceInterface::class,
        'inventory_counts' => InventoryCountServiceInterface::class,
        'stock_transfers' => StockTransferServiceInterface::class,
    ],

    'import_resources' => [
        'suppliers' => SupplierImportDefinition::class,
        'units' => UnitImportDefinition::class,
        'ingredients' => IngredientImportDefinition::class,
    ],
];
