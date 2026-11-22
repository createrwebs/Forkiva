<?php

namespace Modules\Inventory\Enums;

use Modules\Support\Traits\EnumArrayable;
use Modules\Support\Traits\EnumTranslatable;

enum InventoryStockSyncOperation: string
{
    use EnumArrayable, EnumTranslatable;

    case Deduct = 'deduct';
    case Restore = 'restore';

    /** {@inheritDoc} */
    public static function getTransKey(): string
    {
        return 'inventory::enums.inventory_stock_sync_operations';
    }
}
