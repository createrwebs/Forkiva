<?php

namespace Modules\Core\Services\Accounting;

use Modules\Core\Contracts\FinancialPostingStatusReaderInterface;

class NullFinancialPostingStatusReader implements FinancialPostingStatusReaderInterface
{
    /** {@inheritDoc} */
    public function latest(string $sourceType, int $sourceId, string $event): ?array
    {
        return null;
    }

    /** {@inheritDoc} */
    public function latestMany(array $lookups): array
    {
        return [];
    }
}
