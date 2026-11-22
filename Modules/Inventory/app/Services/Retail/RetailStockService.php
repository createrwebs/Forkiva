<?php

namespace Modules\Inventory\Services\Retail;

use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;
use Illuminate\Support\Facades\DB;
use Modules\Inventory\Models\StockLot;

class RetailStockService
{
    /**
     * Convert a multi-UOM quantity (e.g., 2 cases * 12 units/case) into base units.
     */
    public function convertUomToBaseQuantity(string $quantity, string $conversionRate): string
    {
        return (string) BigDecimal::of($quantity)
            ->multipliedBy(BigDecimal::of($conversionRate))
            ->toScale(4, RoundingMode::HALF_UP);
    }

    /**
     * Resolve the lowest eligible wholesale tier unit price for the given quantity.
     *
     * @param  list<array{min_quantity: int|float|string, unit_price: string}>  $tiers
     */
    public function resolveWholesaleTierPrice(array $tiers, int|float|string $quantity, string $defaultPrice): string
    {
        $qty = BigDecimal::of((string) $quantity);
        $bestPrice = BigDecimal::of($defaultPrice)->toScale(2, RoundingMode::HALF_UP);

        foreach ($tiers as $tier) {
            $minQty = BigDecimal::of((string) $tier['min_quantity']);
            $tierPrice = BigDecimal::of((string) $tier['unit_price'])->toScale(2, RoundingMode::HALF_UP);

            if ($qty->isGreaterThanOrEqualTo($minQty) && $tierPrice->isLessThan($bestPrice)) {
                $bestPrice = $tierPrice;
            }
        }

        return (string) $bestPrice;
    }

    /**
     * Deduct stock from active lots using FEFO (First Expired, First Out).
     *
     * @return list<array{lot_id: int, lot_number: string, deducted: string, unit_cost: string}>
     */
    public function deductFefoLots(int $branchId, int $productId, string $quantity, ?int $variantId = null): array
    {
        return DB::transaction(function () use ($branchId, $productId, $quantity, $variantId): array {
            $remainingToDeduct = BigDecimal::of($quantity)->toScale(4, RoundingMode::HALF_UP);
            $allocations = [];

            $lots = StockLot::query()
                ->where('branch_id', $branchId)
                ->where('product_id', $productId)
                ->when($variantId !== null, fn ($q) => $q->where('product_variant_id', $variantId))
                ->where('quantity_remaining', '>', 0)
                ->orderBy('expires_at')
                ->orderBy('id')
                ->lockForUpdate()
                ->get();

            foreach ($lots as $lot) {
                if ($remainingToDeduct->isLessThanOrEqualTo(0)) {
                    break;
                }

                $available = BigDecimal::of((string) $lot->quantity_remaining)->toScale(4, RoundingMode::HALF_UP);
                $take = $available->isLessThan($remainingToDeduct) ? $available : $remainingToDeduct;

                $lot->quantity_remaining = (string) $available->minus($take)->toScale(4, RoundingMode::HALF_UP);
                $lot->save();

                $allocations[] = [
                    'lot_id' => $lot->id,
                    'lot_number' => $lot->lot_number,
                    'deducted' => (string) $take,
                    'unit_cost' => (string) $lot->unit_cost,
                ];

                $remainingToDeduct = $remainingToDeduct->minus($take);
            }

            return $allocations;
        });
    }
}
