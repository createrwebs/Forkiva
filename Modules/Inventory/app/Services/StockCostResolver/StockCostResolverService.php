<?php

namespace Modules\Inventory\Services\StockCostResolver;

use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;
use Illuminate\Validation\ValidationException;
use Modules\Currency\Currency;
use Modules\Inventory\Data\StockMovementData;
use Modules\Inventory\Models\Ingredient;
use Modules\Inventory\Models\InventoryCostBalance;

class StockCostResolverService implements StockCostResolverServiceInterface
{
    private const QUANTITY_PRECISION = 6;

    /** {@inheritDoc} */
    public function resolve(
        StockMovementData $data,
        Ingredient $ingredient,
        InventoryCostBalance $balance,
    ): array {
        if ($balance->requires_remediation) {
            throw ValidationException::withMessages([
                'ingredient_id' => __('inventory::messages.stock_cost_balance_requires_remediation'),
            ]);
        }

        $currency = strtoupper(($data->currency ?: $balance->currency));
        $balanceCost = $balance->getRawOriginal('average_unit_cost');
        $unitCost = $data->unitCost ?? $balanceCost;

        if (blank($currency)) {
            throw ValidationException::withMessages([
                'currency' => __('inventory::messages.stock_movement_currency_missing'),
            ]);
        }

        $isImplicitCost = is_null($data->unitCost);

        if (is_null($unitCost)
            || BigDecimal::of((string) $unitCost)->isLessThan(BigDecimal::zero())
            || ($isImplicitCost && BigDecimal::of((string) $unitCost)->isLessThanOrEqualTo(BigDecimal::zero()))) {
            throw ValidationException::withMessages([
                'unit_cost' => __('inventory::messages.stock_movement_cost_missing'),
            ]);
        }

        return [
            'unit_cost' => $this->scaled($unitCost, self::QUANTITY_PRECISION),
            'currency' => $currency,
        ];
    }

    private function scaled(BigDecimal|string $value, int $scale): string
    {
        return (string) BigDecimal::of((string) $value)->toScale($scale, RoundingMode::HalfUp);
    }

    /** {@inheritDoc} */
    public function apply(
        InventoryCostBalance $balance,
        StockMovementData $data,
        string $unitCost,
    ): array {
        $quantity = BigDecimal::of((string) $balance->getRawOriginal('quantity_on_hand'));
        $value = BigDecimal::of((string) $balance->getRawOriginal('inventory_value'));
        $movementQuantity = BigDecimal::of($data->quantity);
        $movementUnitCost = BigDecimal::of($unitCost);
        $currency = strtoupper(($data->currency ?: $balance->currency));
        $currencyPrecision = Currency::subunit($currency);
        $movementValue = $movementQuantity
            ->multipliedBy($movementUnitCost)
            ->toScale($currencyPrecision, RoundingMode::HalfUp);

        if ($data->type->isIncoming()) {
            $newQuantity = $quantity->plus($movementQuantity);
            $newValue = $value->plus($movementValue)->toScale($currencyPrecision, RoundingMode::HalfUp);
        } else {
            $newQuantity = $quantity->minus($movementQuantity);
            $newValue = $value->minus($movementValue)->toScale($currencyPrecision, RoundingMode::HalfUp);
        }

        if ($newQuantity->toScale(self::QUANTITY_PRECISION, RoundingMode::HalfUp)->isZero()) {
            $newValue = BigDecimal::zero()->toScale($currencyPrecision);
        }

        $currentAverage = BigDecimal::of((string) ($balance->getRawOriginal('average_unit_cost') ?? $unitCost));
        $newAverage = $data->type->isIncoming() && $newQuantity->isGreaterThan(BigDecimal::zero())
            ? $newValue->dividedBy($newQuantity, self::QUANTITY_PRECISION, RoundingMode::HalfUp)
            : $currentAverage;

        return [
            'quantity' => $this->scaled($newQuantity, self::QUANTITY_PRECISION),
            'value' => $this->scaled($newValue, $currencyPrecision),
            'average_cost' => $this->scaled($newAverage, self::QUANTITY_PRECISION),
        ];
    }
}
