<?php

namespace Modules\Invoice\Services\OrderLevelTaxAllocation;

use Illuminate\Support\Collection;
use Modules\Order\Models\Order;
use Modules\Order\Models\OrderTax;

interface OrderLevelTaxAllocationServiceInterface
{
    /**
     * Allocate a proportional share of an order-level tax for the reversed products.
     *
     * @param  Collection<int,int>  $reversedProductIds
     */
    public function allocate(Order $order, Collection $reversedProductIds, OrderTax $orderTax): float;
}
