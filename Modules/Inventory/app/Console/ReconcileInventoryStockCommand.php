<?php

namespace Modules\Inventory\Console;

use Illuminate\Console\Command;
use Modules\Inventory\Services\StockReconciliation\StockReconciliationServiceInterface;

class ReconcileInventoryStockCommand extends Command
{
    /** @var string */
    protected $signature = 'inventory:reconcile-stock {--branch=} {--repair}';

    /** @var string */
    protected $description = 'Compare committed stock movements with Inventory and Accounting balances.';

    /**
     * Execute the stock reconciliation audit.
     */
    public function handle(StockReconciliationServiceInterface $service): int
    {
        $branchId = $this->option('branch') ? (int) $this->option('branch') : null;
        $result = $service->reconcile($branchId, (bool) $this->option('repair'));

        $this->components->info("Checked {$result['checked']} ingredient balance(s).");

        if ($result['discrepancies'] === []) {
            $this->components->info('No stock reconciliation discrepancies were found.');

            return self::SUCCESS;
        }

        $this->table(
            ['Branch', 'Ingredient', 'Differences', 'Repaired', 'Blocked'],
            collect($result['discrepancies'])->map(fn (array $row): array => [
                $row['branch_id'],
                $row['ingredient_id'],
                implode(', ', array_keys($row['differences'])),
                $row['repaired'] ? 'yes' : 'no',
                $row['repair_blocked'] ? 'yes' : 'no',
            ]),
        );
        $this->components->warn(
            count($result['discrepancies'])." discrepancy record(s); {$result['repaired']} repaired, {$result['blocked']} blocked.",
        );

        return $result['blocked'] > 0 || ! $this->option('repair') ? self::FAILURE : self::SUCCESS;
    }
}
