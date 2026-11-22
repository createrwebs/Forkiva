<?php

namespace Modules\Core\Contracts;

interface FinancialAccountLookupInterface
{
    /**
     * Determine whether Accounting can resolve financial accounts.
     */
    public function available(): bool;

    /**
     * Return active financial-account choices for an operational workflow.
     *
     * @return array<int, array<string, mixed>>
     */
    public function list(int $branchId, ?string $currency = null): array;

    /**
     * Resolve one opaque financial-account key for the branch and currency.
     *
     * @return array<string, mixed>|null
     */
    public function resolve(string $key, int $branchId, string $currency): ?array;
}
