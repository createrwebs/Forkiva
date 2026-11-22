<?php

namespace Modules\Inventory\Enums;

use Modules\Support\Traits\EnumArrayable;
use Modules\Support\Traits\EnumTranslatable;

enum InventoryStockSyncStatus: string
{
    use EnumArrayable, EnumTranslatable;

    case PendingStock = 'pending_stock';
    case ProcessingStock = 'processing_stock';
    case PendingAccounting = 'pending_accounting';
    case Completed = 'completed';
    case Failed = 'failed';

    /** {@inheritDoc} */
    public static function getTransKey(): string
    {
        return 'inventory::enums.inventory_stock_sync_statuses';
    }
}
