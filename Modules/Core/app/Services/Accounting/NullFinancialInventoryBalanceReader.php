<?php

namespace Modules\Core\Services\Accounting;

use Modules\Core\Contracts\FinancialInventoryBalanceReaderInterface;

class NullFinancialInventoryBalanceReader implements FinancialInventoryBalanceReaderInterface
{
    /** {@inheritDoc} */
    public function find(int $branchId, int $ingredientId): ?array
    {
        return null;
    }
}
