<?php

namespace Modules\Inventory\Services\SupplierReturnMovement;

use Modules\Inventory\Data\StockMovementData;
use Modules\Inventory\Enums\StockMovementType;
use Modules\Inventory\Models\StockMovement;
use Modules\Inventory\Services\StockMovement\StockMovementServiceInterface;

class SupplierReturnMovementService implements SupplierReturnMovementServiceInterface
{
    /**
     * Create a supplier return movement service instance.
     */
    public function __construct(protected StockMovementServiceInterface $stockMovements) {}

    /** {@inheritDoc} */
    public function reverse(StockMovement $original, string $sourceType, int $sourceId, string $idempotencyKey, ?string $note = null): StockMovement
    {
        $original = StockMovement::query()
            ->withOutGlobalBranchPermission()
            ->withTrashed()
            ->findOrFail($original->id);

        abort_unless($original->type === StockMovementType::ReturnToSupplier, 422, __('inventory::messages.invalid_supplier_return_movement'));

        return $this->stockMovements->record(new StockMovementData(
            branchId: (int) $original->branch_id,
            ingredientId: (int) $original->ingredient_id,
            type: StockMovementType::In,
            quantity: (string) $original->getRawOriginal('quantity'),
            sourceType: $sourceType,
            sourceId: $sourceId,
            idempotencyKey: $idempotencyKey,
            occurredAt: now(),
            currencyRate: (string) $original->getRawOriginal('currency_rate'),
            unitCost: (string) $original->getRawOriginal('unit_cost'),
            currency: (string) $original->currency,
            reversalOfId: $original->id,
            note: $note,
            emitAccounting: false,
        ));
    }

    /** {@inheritDoc} */
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
    ): StockMovement {
        return $this->stockMovements->record(new StockMovementData(
            branchId: $branchId,
            ingredientId: $ingredientId,
            type: StockMovementType::ReturnToSupplier,
            quantity: $quantity,
            sourceType: $sourceType,
            sourceId: $sourceId,
            idempotencyKey: $idempotencyKey,
            occurredAt: now(),
            currencyRate: $currencyRate,
            unitCost: $unitCost,
            currency: $currency,
            note: $note,
            emitAccounting: false,
        ));
    }
}
