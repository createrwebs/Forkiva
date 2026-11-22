<?php

namespace Modules\Invoice\Services\FullOrderReversal;

use Modules\Order\Enums\OrderProductStatus;
use Modules\Order\Models\Order;

class FullOrderReversalResolverService implements FullOrderReversalResolverServiceInterface
{
    /** {@inheritDoc} */
    public function isFullOrderReversal(Order $order): bool
    {
        $products = $order->products()->get(['id', 'product_id', 'status']);

        if ($products->isEmpty()) {
            return false;
        }

        return $products->every(fn ($product) => in_array(
            $product->status,
            [OrderProductStatus::Cancelled, OrderProductStatus::Refunded],
            true
        ));
    }
}
