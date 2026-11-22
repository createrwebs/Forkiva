<?php

namespace Modules\Inventory\Services\StockCostResolver;

use Modules\Inventory\Data\StockMovementData;
use Modules\Inventory\Models\Ingredient;
use Modules\Inventory\Models\InventoryCostBalance;

interface StockCostResolverServiceInterface
{
    /**
     * Resolve the immutable unit cost and currency for a movement.
     *
     * @param  StockMovementData  $data  Trusted movement input.
     * @param  Ingredient  $ingredient  Locked ingredient.
     * @param  InventoryCostBalance  $balance  Locked moving-average balance.
     * @return array{unit_cost: string, currency: string}
     */
    public function resolve(
        StockMovementData $data,
        Ingredient $ingredient,
        InventoryCostBalance $balance,
    ): array;

    /**
     * Apply a committed movement to the locked cost balance.
     *
     * @return array{quantity: string, value: string, average_cost: string}
     */
    public function apply(
        InventoryCostBalance $balance,
        StockMovementData $data,
        string $unitCost,
    ): array;
}
