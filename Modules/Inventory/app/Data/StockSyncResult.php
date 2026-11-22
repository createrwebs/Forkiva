<?php

namespace Modules\Inventory\Data;

use Illuminate\Support\Collection;
use Modules\Inventory\Models\StockMovement;

readonly class StockSyncResult
{
    /**
     * Create a stock synchronization result from committed movement snapshots.
     *
     * @param  Collection<int, StockMovement>  $movements  Committed movements created or reused by the operation.
     * @param  array<int, string>  $ingredientQuantities  Quantities keyed by ingredient identifier.
     * @param  array<int, array<int, string>>  $unitCosts  Frozen costs keyed by ingredient and movement identifiers.
     * @param  array<int, array<int, string>>  $currencies  Frozen currencies keyed by ingredient and movement identifiers.
     * @param  string  $orderCost  Total order cost calculated from persisted movement snapshots.
     * @param  string  $grossProfit  Gross profit after the frozen stock cost.
     */
    public function __construct(
        public Collection $movements,
        public array $ingredientQuantities,
        public array $unitCosts,
        public array $currencies,
        public string $orderCost,
        public string $grossProfit,
    ) {}

    /**
     * Get the durable payload stored on the stock synchronization job.
     *
     * @return array<string, mixed>
     */
    public function toPayload(): array
    {
        return [
            'payload_version' => 2,
            'movement_ids' => $this->movementIds(),
            'ingredient_quantities' => $this->ingredientQuantities,
            'unit_costs' => $this->unitCosts,
            'currencies' => $this->currencies,
            'order_cost' => $this->orderCost,
            'gross_profit' => $this->grossProfit,
        ];
    }

    /**
     * Get committed movement identifiers in deterministic order.
     *
     * @return array<int, int>
     */
    public function movementIds(): array
    {
        return $this->movements
            ->pluck('id')
            ->map(fn (mixed $id): int => (int) $id)
            ->values()
            ->all();
    }
}
