<?php

namespace Modules\Inventory\Services\StockReconciliation;

use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;
use Illuminate\Support\Facades\DB;
use Modules\Core\Contracts\FinancialInventoryBalanceReaderInterface;
use Modules\Currency\Currency;
use Modules\Inventory\Enums\StockMovementType;
use Throwable;

class StockReconciliationService implements StockReconciliationServiceInterface
{
    private const PRECISION = 6;

    /**
     * Inventory-owned discrepancy keys that repair mode may update.
     *
     * Accounting differences remain visible for audit and must be repaired by
     * Accounting rather than being reported as fixed by Inventory.
     */
    private const REPAIRABLE_DIFFERENCE_KEYS = [
        'ingredient_quantity',
        'ingredient_average_cost',
        'cost_balance_missing',
        'cost_balance_quantity',
        'cost_balance_value',
        'cost_balance_average',
        'cost_balance_currency',
    ];

    public function __construct(protected FinancialInventoryBalanceReaderInterface $financialBalances) {}

    /** {@inheritDoc} */
    public function reconcile(?int $branchId = null, bool $repair = false): array
    {
        $result = [
            'checked' => 0,
            'discrepancies' => [],
            'repaired' => 0,
            'blocked' => 0,
        ];

        DB::table('ingredients')
            ->select(['id', 'branch_id'])
            ->when($branchId, fn ($query) => $query->where('branch_id', $branchId))
            ->chunkById(200, function ($ingredients) use (&$result, $repair): void {
                foreach ($ingredients as $ingredient) {
                    $comparison = $this->compareIngredient(
                        branchId: (int) $ingredient->branch_id,
                        ingredientId: (int) $ingredient->id,
                        repair: $repair,
                    );

                    $result['checked']++;

                    if (! $comparison['has_discrepancy']) {
                        continue;
                    }

                    $result['discrepancies'][] = $comparison;
                    $result['repaired'] += $comparison['repaired'] ? 1 : 0;
                    $result['blocked'] += $comparison['repair_blocked'] ? 1 : 0;
                }
            });

        return $result;
    }

    /**
     * Compare and optionally repair one ingredient while holding its write lock.
     *
     * @return array<string, mixed>
     *
     * @throws Throwable
     */
    private function compareIngredient(int $branchId, int $ingredientId, bool $repair): array
    {
        return DB::transaction(function () use ($branchId, $ingredientId, $repair): array {
            $ingredient = DB::table('ingredients')
                ->where('branch_id', $branchId)
                ->where('id', $ingredientId)
                ->lockForUpdate()
                ->first(['id', 'branch_id', 'current_stock', 'cost_per_unit']);
            $currency = strtoupper((string) DB::table('branches')->where('id', $branchId)->value('currency'));
            $currencyPrecision = Currency::subunit($currency);
            $balance = DB::table('inventory_cost_balances')
                ->where('branch_id', $branchId)
                ->where('ingredient_id', $ingredientId)
                ->first();
            $movements = DB::table('stock_movements')
                ->where('branch_id', $branchId)
                ->where('ingredient_id', $ingredientId)
                ->whereNull('deleted_at')
                ->when(
                    $balance?->reconciliation_baseline_movement_id,
                    fn ($query, $movementId) => $query->where('id', '>', $movementId),
                    fn ($query) => $query->when(
                        $balance?->reconciliation_baseline_at,
                        fn ($query, $baseline) => $query->where('committed_at', '>', $baseline),
                    ),
                )
                ->orderBy('committed_at')
                ->orderBy('id')
                ->get(['id', 'type', 'quantity', 'unit_id', 'unit_cost', 'currency']);

            $movementQuantity = $this->decimal($balance?->baseline_quantity ?? 0);
            $movementValue = $this->decimal($balance?->baseline_inventory_value ?? 0);
            $initialAverage = $balance?->baseline_average_unit_cost
                ?? $balance?->average_unit_cost
                ?? $ingredient?->cost_per_unit;
            $lastAverage = ! is_null($initialAverage)
            && $this->decimal($initialAverage)->isGreaterThan(BigDecimal::zero())
                ? $this->decimal($initialAverage)
                : null;
            $invalidMovementIds = [];

            foreach ($movements as $movement) {
                $type = StockMovementType::tryFrom((string) $movement->type);
                $quantity = $this->decimal($movement->quantity);
                $unitCost = is_null($movement->unit_cost) ? null : $this->decimal($movement->unit_cost);
                $movementCurrency = strtoupper((string) $movement->currency);

                if (! $type
                    || $quantity->isLessThanOrEqualTo(BigDecimal::zero())
                    || is_null($movement->unit_id)
                    || $unitCost === null
                    || $unitCost->isLessThan(BigDecimal::zero())
                    || $movementCurrency !== $currency) {
                    $invalidMovementIds[] = (int) $movement->id;

                    continue;
                }

                $movementCost = $quantity
                    ->multipliedBy($unitCost)
                    ->toScale($currencyPrecision, RoundingMode::HalfUp);
                $movementQuantity = $type->isIncoming()
                    ? $movementQuantity->plus($quantity)
                    : $movementQuantity->minus($quantity);
                $movementValue = ($type->isIncoming()
                    ? $movementValue->plus($movementCost)
                    : $movementValue->minus($movementCost))
                    ->toScale($currencyPrecision, RoundingMode::HalfUp);

                // Match StockCostResolverService exactly: receipts recalculate the
                // moving average while outbound movements preserve the last valid
                // average. A true zero balance clears residual rounding value.
                if ($type->isIncoming() && $movementQuantity->isGreaterThan(BigDecimal::zero())) {
                    $lastAverage = $movementValue->dividedBy(
                        $movementQuantity,
                        self::PRECISION,
                        RoundingMode::HalfUp,
                    );
                } elseif ($movementQuantity->toScale(self::PRECISION, RoundingMode::HalfUp)->isZero()) {
                    $movementValue = BigDecimal::zero()->toScale($currencyPrecision);
                }
            }

            $movementQuantity = $this->scaled($movementQuantity, self::PRECISION);
            $movementValue = $this->scaled($movementValue, $currencyPrecision);
            $averageCost = is_null($lastAverage) ? null : $this->scaled($lastAverage, self::PRECISION);
            $accounting = $this->financialBalances->find($branchId, $ingredientId);

            $differences = array_filter([
                'ingredient_quantity' => ! $this->equal($ingredient?->current_stock, $movementQuantity)
                    ? ['actual' => $this->scaled($ingredient?->current_stock ?? 0, self::PRECISION), 'expected' => $movementQuantity]
                    : null,
                'ingredient_average_cost' => ! $this->equal($ingredient?->cost_per_unit, $averageCost, 4)
                    ? [
                        'actual' => $this->scaled($ingredient?->cost_per_unit ?? 0, 4),
                        'expected' => is_null($averageCost) ? null : $this->scaled($averageCost, 4),
                    ]
                    : null,
                'cost_balance_missing' => ! $balance ? true : null,
                'cost_balance_quantity' => $balance && ! $this->equal($balance->quantity_on_hand, $movementQuantity)
                    ? ['actual' => $this->scaled($balance->quantity_on_hand, self::PRECISION), 'expected' => $movementQuantity]
                    : null,
                'cost_balance_value' => $balance && ! $this->equal(
                    $balance->inventory_value,
                    $movementValue,
                    $currencyPrecision,
                )
                    ? ['actual' => $this->scaled($balance->inventory_value, $currencyPrecision), 'expected' => $movementValue]
                    : null,
                'cost_balance_average' => $balance && ! $this->equal($balance->average_unit_cost, $averageCost)
                    ? ['actual' => $this->scaled($balance->average_unit_cost, self::PRECISION), 'expected' => $averageCost]
                    : null,
                'cost_balance_currency' => $balance && strtoupper((string) $balance->currency) !== $currency
                    ? ['actual' => $balance->currency, 'expected' => $currency]
                    : null,
                'accounting_quantity' => $accounting && ! $this->equal($accounting['quantity'], $movementQuantity)
                    ? ['actual' => $accounting['quantity'], 'expected' => $movementQuantity]
                    : null,
                'accounting_value' => $accounting && ! $this->equal(
                    $accounting['value'],
                    $movementValue,
                    $currencyPrecision,
                )
                    ? ['actual' => $accounting['value'], 'expected' => $movementValue]
                    : null,
                'invalid_movement_snapshots' => $invalidMovementIds ?: null,
            ], fn ($value) => $value !== null);

            $repairBlocked = $invalidMovementIds !== [];
            $repaired = false;

            $hasRepairableDifferences = array_intersect(
                array_keys($differences),
                self::REPAIRABLE_DIFFERENCE_KEYS,
            ) !== [];

            if ($repair && $hasRepairableDifferences && ! $repairBlocked) {
                DB::table('ingredients')->where('id', $ingredientId)->update([
                    'current_stock' => $movementQuantity,
                    'cost_per_unit' => $averageCost,
                    'updated_at' => now(),
                ]);
                DB::table('inventory_cost_balances')->updateOrInsert(
                    ['branch_id' => $branchId, 'ingredient_id' => $ingredientId],
                    [
                        'quantity_on_hand' => $movementQuantity,
                        'inventory_value' => $movementValue,
                        'average_unit_cost' => $averageCost,
                        'currency' => $currency,
                        'requires_remediation' => false,
                        'remediation_reason' => null,
                        'created_at' => $balance?->created_at ?? now(),
                        'updated_at' => now(),
                    ],
                );
                $repaired = true;
            }

            return [
                'branch_id' => $branchId,
                'ingredient_id' => $ingredientId,
                'has_discrepancy' => $differences !== [],
                'differences' => $differences,
                'repaired' => $repaired,
                'repair_blocked' => $repairBlocked,
            ];
        }, attempts: 3);
    }

    private function decimal(mixed $value): BigDecimal
    {
        return BigDecimal::of((string) ($value ?? 0));
    }

    private function scaled(mixed $value, int $precision): string
    {
        return (string) $this->decimal($value)->toScale($precision, RoundingMode::HalfUp);
    }

    /**
     * Compare persisted decimal values using movement storage precision.
     */
    private function equal(mixed $left, mixed $right, int $precision = self::PRECISION): bool
    {
        if ($left === null || $right === null) {
            return $left === $right;
        }

        return $this->scaled($left, $precision) === $this->scaled($right, $precision);
    }
}
