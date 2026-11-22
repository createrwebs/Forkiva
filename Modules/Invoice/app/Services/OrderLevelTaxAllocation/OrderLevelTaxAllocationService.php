<?php

namespace Modules\Invoice\Services\OrderLevelTaxAllocation;

use Illuminate\Support\Collection;
use Modules\Currency\Currency;
use Modules\Order\Models\Order;
use Modules\Order\Models\OrderTax;

class OrderLevelTaxAllocationService implements OrderLevelTaxAllocationServiceInterface
{
    /** {@inheritDoc} */
    public function allocate(Order $order, Collection $reversedProductIds, OrderTax $orderTax): float
    {
        if ($reversedProductIds->isEmpty()) {
            return 0.0;
        }

        $refundedSubtotal = (float) $order->products()
            ->whereKey($reversedProductIds->all())
            ->sum('subtotal');

        $eligibleSubtotal = (float) $order->products()->sum('subtotal');

        if ($refundedSubtotal <= 0 || $eligibleSubtotal <= 0) {
            return 0.0;
        }

        $allocatedAmount = ($refundedSubtotal / $eligibleSubtotal) * $orderTax->amount->amount();

        return round($allocatedAmount, Currency::subunit($orderTax->currency));
    }
}
