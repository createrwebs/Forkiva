<?php

namespace Modules\Invoice\Services\DiscountBuilder;

use Illuminate\Support\Collection;
use Modules\Invoice\Models\Invoice;

interface DiscountBuilderServiceInterface
{
    /**
     * Create discounts
     *
     * @param  Collection<int,int>|null  $orderProductIds
     */
    public function createDiscounts(
        Invoice $invoice,
        Collection $orders,
        ?Collection $orderProductIds = null,
        bool $fullOrderReversal = false,
    ): Collection;
}
