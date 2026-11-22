<?php

namespace Modules\Invoice\Services\OrderChargeInvoicePolicy;

use Modules\Order\Models\OrderCharge;

interface OrderChargeInvoicePolicyResolverServiceInterface
{
    /**
     * Determine whether the charge may be reversed on a full-order credit note.
     */
    public function shouldRefundOnFullOrderReversal(OrderCharge $charge): bool;

    /**
     * Determine whether the charge is taxable under the current policy.
     */
    public function isTaxable(OrderCharge $charge): bool;

    /**
     * Resolve structured tax metadata for a charge.
     *
     * @return array<int,array<string,mixed>>
     */
    public function taxMetadata(OrderCharge $charge): array;
}
