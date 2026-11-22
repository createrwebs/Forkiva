<?php

namespace Modules\Core\Contracts;

use JsonException;
use Modules\Support\Money;

interface FinancialExpenseSummaryReaderInterface
{
    /**
     * Read posted operating expenses for one branch and accounting date.
     * The returned currency follows the ledger; null means the optional summary is unavailable.
     *
     * @throws JsonException
     */
    public function forDate(int $branchId, string $date): ?Money;
}
