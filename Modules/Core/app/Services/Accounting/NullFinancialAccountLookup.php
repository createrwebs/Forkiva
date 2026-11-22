<?php

namespace Modules\Core\Services\Accounting;

use Modules\Core\Contracts\FinancialAccountLookupInterface;

class NullFinancialAccountLookup implements FinancialAccountLookupInterface
{
    /** {@inheritDoc} */
    public function available(): bool
    {
        return false;
    }

    /** {@inheritDoc} */
    public function list(int $branchId, ?string $currency = null): array
    {
        return [];
    }

    /** {@inheritDoc} */
    public function resolve(string $key, int $branchId, string $currency): ?array
    {
        return null;
    }
}
