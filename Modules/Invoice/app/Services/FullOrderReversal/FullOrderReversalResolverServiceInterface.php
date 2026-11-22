<?php

namespace Modules\Invoice\Services\FullOrderReversal;

use Modules\Order\Models\Order;

interface FullOrderReversalResolverServiceInterface
{
    /**
     * Determine whether every relevant billable product on the order
     * has reached a reversed terminal state.
     */
    public function isFullOrderReversal(Order $order): bool;
}
