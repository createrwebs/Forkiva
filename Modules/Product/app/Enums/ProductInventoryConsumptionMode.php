<?php

namespace Modules\Product\Enums;

use Modules\Support\Traits\EnumArrayable;
use Modules\Support\Traits\EnumTranslatable;

/** Determine which bill of materials an order consumes for a product. */
enum ProductInventoryConsumptionMode: string
{
    use EnumArrayable, EnumTranslatable;

    case DirectRecipe = 'direct_recipe';
    case ProductionOutput = 'production_output';
    case DirectProduct = 'direct_product';

    /** {@inheritDoc} */
    public static function getTransKey(): string
    {
        return 'product::enums.inventory_consumption_modes';
    }
}
