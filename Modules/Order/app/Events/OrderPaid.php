<?php

namespace Modules\Order\Events;

use Modules\Order\Models\Order;

class OrderPaid
{
    /**
     * Create a new event instance.
     */
    public function __construct(public Order $order) {}
}
