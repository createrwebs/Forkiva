<?php

namespace Modules\Inventory\Enums;

use Modules\Support\Traits\EnumArrayable;
use Modules\Support\Traits\EnumTranslatable;

enum InventoryCountStatus: string
{
    use EnumArrayable, EnumTranslatable;

    case Draft = 'draft';
    case Approved = 'approved';
    case Cancelled = 'cancelled';

    /** {@inheritDoc} */
    public static function getTransKey(): string
    {
        return 'inventory::enums.inventory_count_statuses';
    }

    /**
     * Get the presentation color used by status components.
     */
    public function color(): string
    {
        return match ($this) {
            self::Draft => 'info',
            self::Approved => 'success',
            self::Cancelled => 'error',
        };
    }
}
