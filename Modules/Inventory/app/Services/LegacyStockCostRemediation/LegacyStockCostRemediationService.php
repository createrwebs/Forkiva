<?php

namespace Modules\Inventory\Services\LegacyStockCostRemediation;

use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;
use Illuminate\Support\Facades\DB;
use Modules\Currency\Currency;
use Modules\Inventory\Enums\StockMovementType;
use Throwable;

class LegacyStockCostRemediationService implements LegacyStockCostRemediationServiceInterface
{
    private const SCALE = 6;

    /** {@inheritDoc} */
    public function remediate(bool $apply = false, ?int $branchId = null): array
    {
        $summary = [
            'legacy_movements' => 0,
            'source_document' => 0,
            'reconstructed_moving_average' => 0,
            'estimated_current_ingredient_cost' => 0,
            'balance_mismatches' => 0,
            'reconciliation_baselines' => 0,
            'remediated' => 0,
        ];

        $ingredients = DB::table('stock_movements')
            ->where('legacy_cost_snapshot_missing', true)
            ->when($branchId, fn ($query) => $query->where('branch_id', $branchId))
            ->select(['branch_id', 'ingredient_id'])
            ->distinct()
            ->orderBy('branch_id')
            ->orderBy('ingredient_id')
            ->get();

        foreach ($ingredients as $key) {
            $result = $this->remediateIngredient(
                (int) $key->branch_id,
                (int) $key->ingredient_id,
                $apply,
            );

            foreach ($result as $name => $count) {
                $summary[$name] += $count;
            }
        }

        return $summary;
    }

    /**
     * @return array<string, int>
     *
     * @throws Throwable
     */
    private function remediateIngredient(int $branchId, int $ingredientId, bool $apply): array
    {
        return DB::transaction(function () use ($branchId, $ingredientId, $apply): array {
            $result = [
                'legacy_movements' => 0,
                'source_document' => 0,
                'reconstructed_moving_average' => 0,
                'estimated_current_ingredient_cost' => 0,
                'balance_mismatches' => 0,
                'reconciliation_baselines' => 0,
                'remediated' => 0,
            ];
            $branch = DB::table('branches')->where('id', $branchId)->first(['currency']);
            $ingredient = DB::table('ingredients')
                ->where('id', $ingredientId)
                ->lockForUpdate()
                ->first(['current_stock', 'cost_per_unit']);
            $currency = strtoupper((string) $branch?->currency);
            $currencyPrecision = Currency::subunit($currency);
            $fallbackCost = $this->decimal($ingredient?->cost_per_unit ?? 0);
            $quantityOnHand = BigDecimal::zero();
            $inventoryValue = BigDecimal::zero();
            $averageCost = null;

            $movements = DB::table('stock_movements')
                ->where('branch_id', $branchId)
                ->where('ingredient_id', $ingredientId)
                ->orderBy('occurred_at')
                ->orderBy('id')
                ->lockForUpdate()
                ->get([
                    'id',
                    'type',
                    'quantity',
                    'unit_cost',
                    'currency',
                    'source_type',
                    'source_id',
                    'legacy_cost_snapshot_missing',
                    'deleted_at',
                ]);

            foreach ($movements as $movement) {
                $type = StockMovementType::tryFrom((string) $movement->type);
                if (! $type) {
                    continue;
                }

                $quantity = $this->decimal($movement->quantity);
                $unitCost = is_null($movement->unit_cost)
                    ? null
                    : $this->decimal($movement->unit_cost);
                $source = null;

                if ($movement->legacy_cost_snapshot_missing) {
                    $result['legacy_movements']++;
                    $sourceCost = $this->sourceDocumentCost($movement, $ingredientId);

                    if ($sourceCost !== null) {
                        $unitCost = $sourceCost;
                        $source = 'source_document';
                    } elseif ($averageCost !== null && $averageCost->isGreaterThan(BigDecimal::zero())) {
                        $unitCost = $averageCost;
                        $source = 'reconstructed_moving_average';
                    } else {
                        $unitCost = $fallbackCost;
                        $source = 'estimated_current_ingredient_cost';
                    }

                    $result[$source]++;

                    if ($apply) {
                        DB::table('stock_movements')->where('id', $movement->id)->update([
                            'unit_cost' => $this->scaled($unitCost, self::SCALE),
                            'currency' => $currency,
                            'legacy_cost_snapshot_missing' => false,
                            'cost_snapshot_source' => $source,
                            'cost_snapshot_remediated_at' => now(),
                            'updated_at' => now(),
                        ]);
                        $result['remediated']++;
                    }
                }

                if ($unitCost === null) {
                    continue;
                }

                if (! is_null($movement->deleted_at)) {
                    continue;
                }

                $movementValue = $quantity
                    ->multipliedBy($unitCost)
                    ->toScale($currencyPrecision, RoundingMode::HalfUp);
                $quantityOnHand = $type->isIncoming()
                    ? $quantityOnHand->plus($quantity)
                    : $quantityOnHand->minus($quantity);
                $inventoryValue = ($type->isIncoming()
                    ? $inventoryValue->plus($movementValue)
                    : $inventoryValue->minus($movementValue))
                    ->toScale($currencyPrecision, RoundingMode::HalfUp);

                if ($quantityOnHand->toScale(self::SCALE, RoundingMode::HalfUp)->isZero()) {
                    $inventoryValue = BigDecimal::zero()->toScale($currencyPrecision);
                }

                if ($type->isIncoming() && $quantityOnHand->isGreaterThan(BigDecimal::zero())) {
                    $averageCost = $inventoryValue->dividedBy(
                        $quantityOnHand,
                        self::SCALE,
                        RoundingMode::HalfUp,
                    );
                } elseif ($averageCost === null) {
                    $averageCost = $unitCost;
                }
            }

            if ($apply) {
                $balanceMismatch = $this->scaled($quantityOnHand, self::SCALE)
                    !== $this->scaled($this->decimal($ingredient?->current_stock ?? 0), self::SCALE);
                $result['balance_mismatches'] += $balanceMismatch ? 1 : 0;
                $baselineAt = $balanceMismatch ? now() : null;
                $persistedQuantity = $balanceMismatch
                    ? $this->decimal($ingredient?->current_stock ?? 0)
                    : $quantityOnHand;
                $persistedAverage = $balanceMismatch
                    ? $fallbackCost
                    : ($averageCost ?? $fallbackCost);
                $persistedValue = $balanceMismatch
                    ? $persistedQuantity
                        ->multipliedBy($persistedAverage)
                        ->toScale($currencyPrecision, RoundingMode::HalfUp)
                    : $inventoryValue;
                $result['reconciliation_baselines'] += $balanceMismatch ? 1 : 0;
                $balanceCreatedAt = DB::table('inventory_cost_balances')
                    ->where('branch_id', $branchId)
                    ->where('ingredient_id', $ingredientId)
                    ->value('created_at');

                DB::table('inventory_cost_balances')->updateOrInsert(
                    ['branch_id' => $branchId, 'ingredient_id' => $ingredientId],
                    [
                        'quantity_on_hand' => $this->scaled($persistedQuantity, self::SCALE),
                        'inventory_value' => $this->scaled($persistedValue, $currencyPrecision),
                        'average_unit_cost' => $this->scaled($persistedAverage, self::SCALE),
                        'currency' => $currency,
                        'requires_remediation' => false,
                        'remediation_reason' => null,
                        'reconciliation_baseline_at' => $baselineAt,
                        'reconciliation_baseline_movement_id' => $baselineAt
                            ? $movements->max('id')
                            : null,
                        'baseline_quantity' => $baselineAt
                            ? $this->scaled($persistedQuantity, self::SCALE)
                            : null,
                        'baseline_inventory_value' => $baselineAt
                            ? $this->scaled($persistedValue, $currencyPrecision)
                            : null,
                        'baseline_average_unit_cost' => $baselineAt
                            ? $this->scaled($persistedAverage, self::SCALE)
                            : null,
                        'created_at' => $balanceCreatedAt ?? now(),
                        'updated_at' => now(),
                    ],
                );
                if (! $balanceMismatch) {
                    DB::table('ingredients')->where('id', $ingredientId)->update([
                        'cost_per_unit' => $this->scaled($averageCost ?? $fallbackCost, self::SCALE),
                        'updated_at' => now(),
                    ]);
                }
            } elseif ($this->scaled($quantityOnHand, self::SCALE)
                !== $this->scaled($this->decimal($ingredient?->current_stock ?? 0), self::SCALE)) {
                $result['balance_mismatches']++;
                $result['reconciliation_baselines']++;
            }

            return $result;
        }, attempts: 3);
    }

    private function decimal(mixed $value): BigDecimal
    {
        return BigDecimal::of((string) ($value ?? 0));
    }

    private function sourceDocumentCost(object $movement, int $ingredientId): ?BigDecimal
    {
        $sourceType = ltrim((string) $movement->source_type, '\\');

        if ($sourceType === 'Modules\\Inventory\\Models\\Purchase') {
            $cost = DB::table('purchase_items')
                ->where('purchase_id', $movement->source_id)
                ->where('ingredient_id', $ingredientId)
                ->value('unit_cost');

            return is_null($cost) ? null : $this->decimal($cost);
        }

        if ($sourceType === 'Modules\\Inventory\\Models\\PurchaseReceiptItem') {
            $cost = DB::table('purchase_receipt_items as receipt_item')
                ->join('purchase_items as item', 'item.id', '=', 'receipt_item.purchase_item_id')
                ->where('receipt_item.id', $movement->source_id)
                ->where('item.ingredient_id', $ingredientId)
                ->value('item.unit_cost');

            return is_null($cost) ? null : $this->decimal($cost);
        }

        return null;
    }

    private function scaled(BigDecimal $value, int $scale): string
    {
        return (string) $value->toScale($scale, RoundingMode::HalfUp);
    }
}
