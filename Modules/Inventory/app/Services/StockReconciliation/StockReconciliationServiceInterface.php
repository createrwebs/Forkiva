<?php

namespace Modules\Inventory\Services\StockReconciliation;

interface StockReconciliationServiceInterface
{
    /**
     * Compare committed movement history with operational and Accounting balances.
     *
     * Repair mode updates only Inventory-derived ingredient and cost-balance state
     * when every movement has a complete, branch-currency cost snapshot. Posted
     * Accounting records are reported but never changed by reconciliation.
     *
     * @param  int|null  $branchId  Optional branch restriction.
     * @param  bool  $repair  Whether safe Inventory discrepancies should be repaired.
     * @return array{
     *     checked: int,
     *     discrepancies: array<int, array<string, mixed>>,
     *     repaired: int,
     *     blocked: int
     * }
     */
    public function reconcile(?int $branchId = null, bool $repair = false): array;
}
