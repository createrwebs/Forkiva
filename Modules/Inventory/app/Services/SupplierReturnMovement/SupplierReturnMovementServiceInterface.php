<?php

namespace Modules\Inventory\Services\SupplierReturnMovement;

use Modules\Inventory\Models\StockMovement;
use Throwable;

interface SupplierReturnMovementServiceInterface
{
    /**
     * Create one idempotent outgoing supplier-return movement.
     *
     * @throws Throwable
     */
    public function create(
        int $branchId,
        int $ingredientId,
        int|float|string $quantity,
        string $sourceType,
        int $sourceId,
        string $idempotencyKey,
        int|float|string $unitCost,
        string $currency,
        int|float|string $currencyRate,
        ?string $note = null,
    ): StockMovement;

    /**
     * Create one idempotent incoming movement that reverses a supplier return.
     *
     * @throws Throwable
     */
    public function reverse(StockMovement $original, string $sourceType, int $sourceId, string $idempotencyKey, ?string $note = null): StockMovement;
}
