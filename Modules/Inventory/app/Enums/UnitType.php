<?php

namespace Modules\Inventory\Enums;

use Modules\Support\Traits\EnumArrayable;
use Modules\Support\Traits\EnumTranslatable;

enum UnitType: string
{
    use EnumArrayable, EnumTranslatable;

    case Mass = 'mass';
    case Volume = 'volume';
    case Count = 'count';
    case Custom = 'custom';

    /** {@inheritDoc} */
    public static function getTransKey(): string
    {
        return 'inventory::enums.unit_types';
    }
}
