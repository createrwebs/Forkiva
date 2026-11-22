<?php

namespace Modules\Inventory\Services\LegacyStockCostRemediation;

use Throwable;

interface LegacyStockCostRemediationServiceInterface
{
    /**
     * Audit or remediate historical movements without immutable cost snapshots.
     *
     * @return array<string, int>
     *
     * @throws Throwable
     */
    public function remediate(bool $apply = false, ?int $branchId = null): array;
}
