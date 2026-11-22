<?php

namespace Modules\Core\Contracts;

interface FinancialInventoryBalanceReaderInterface
{
    /**
     * Read an optional Accounting valuation balance.
     *
     * @return array{quantity: string, value: string}|null
     */
    public function find(int $branchId, int $ingredientId): ?array;
}
