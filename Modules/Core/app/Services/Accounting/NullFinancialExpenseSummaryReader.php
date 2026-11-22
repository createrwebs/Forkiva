<?php

namespace Modules\Core\Services\Accounting;

use Modules\Core\Contracts\FinancialExpenseSummaryReaderInterface;
use Modules\Support\Money;

class NullFinancialExpenseSummaryReader implements FinancialExpenseSummaryReaderInterface
{
    /** {@inheritDoc} */
    public function forDate(int $branchId, string $date): ?Money
    {
        return null;
    }
}
