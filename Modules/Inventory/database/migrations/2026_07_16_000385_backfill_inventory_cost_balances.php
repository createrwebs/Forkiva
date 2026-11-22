<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Modules\Currency\Currency;

return new class extends Migration
{
    private const PRECISION = 6;

    /**
     * Rebuild authoritative balances only from complete historical snapshots.
     */
    public function up(): void
    {
        DB::table('ingredients')
            ->select(['id', 'branch_id', 'current_stock'])
            ->chunkById(200, function ($ingredients): void {
                foreach ($ingredients as $ingredient) {
                    $this->backfillIngredient($ingredient);
                }
            });
    }

    /**
     * Remove derived balances when rolling back the backfill.
     */
    public function down(): void
    {
        DB::table('inventory_cost_balances')->delete();
    }

    /**
     * Replay one ingredient's movement history without inventing missing costs.
     */
    private function backfillIngredient(object $ingredient): void
    {
        $branchCurrency = DB::table('branches')
            ->where('id', $ingredient->branch_id)
            ->value('currency');
        $currencyPrecision = Currency::subunit(strtoupper((string) $branchCurrency));
        $movements = DB::table('stock_movements')
            ->where('branch_id', $ingredient->branch_id)
            ->where('ingredient_id', $ingredient->id)
            ->whereNull('deleted_at')
            ->orderBy('created_at')
            ->orderBy('id')
            ->get(['type', 'quantity', 'unit_cost', 'currency']);

        $quantity = 0.0;
        $value = 0.0;
        $average = null;
        $reason = null;

        foreach ($movements as $movement) {
            $movementQuantity = abs((float) $movement->quantity);
            $currency = strtoupper((string) $movement->currency);
            $unitCost = is_null($movement->unit_cost) ? null : (float) $movement->unit_cost;

            if ($unitCost === null || $unitCost < 0 || blank($currency) || $currency !== strtoupper((string) $branchCurrency)) {
                $reason = 'historical_movement_cost_snapshot_missing';
                break;
            }

            $type = $this->normalizedType((string) $movement->type, (float) $movement->quantity);
            $incoming = in_array($type, ['in', 'adjust_add', 'transfer_in'], true);
            $movementValue = round($movementQuantity * $unitCost, $currencyPrecision);

            if ($incoming) {
                $quantity += $movementQuantity;
                $value = round($value + $movementValue, $currencyPrecision);
                $average = $quantity > 0 ? $value / $quantity : $average;
            } else {
                $quantity -= $movementQuantity;
                $value = round($value - $movementValue, $currencyPrecision);
                $average ??= $unitCost;

                if (round($quantity, self::PRECISION) === 0.0) {
                    $value = 0.0;
                }
            }
        }

        if ($reason === null && round($quantity, 4) !== round((float) $ingredient->current_stock, 4)) {
            $reason = 'historical_quantity_does_not_match_ingredient';
        }

        if ($movements->isEmpty() && round((float) $ingredient->current_stock, 4) !== 0.0) {
            $reason = 'stock_exists_without_movement_history';
        }

        DB::table('inventory_cost_balances')->updateOrInsert(
            [
                'branch_id' => $ingredient->branch_id,
                'ingredient_id' => $ingredient->id,
            ],
            [
                'quantity_on_hand' => round($quantity, self::PRECISION),
                'inventory_value' => round($value, $currencyPrecision),
                'average_unit_cost' => is_null($average) ? null : round($average, self::PRECISION),
                'currency' => strtoupper((string) $branchCurrency),
                'requires_remediation' => $reason !== null,
                'remediation_reason' => $reason,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        );
    }

    /**
     * Normalize legacy signed movement direction before the commit migration runs.
     */
    private function normalizedType(string $type, float $quantity): string
    {
        if ($quantity >= 0) {
            return $type;
        }

        return match ($type) {
            'in' => 'out',
            'out' => 'in',
            'adjust_add' => 'adjust_subtract',
            'adjust_subtract' => 'adjust_add',
            'transfer_in' => 'transfer_out',
            'transfer_out' => 'transfer_in',
            default => $type,
        };
    }
};
