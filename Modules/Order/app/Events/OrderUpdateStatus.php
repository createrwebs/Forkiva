<?php

namespace Modules\Order\Events;

use Modules\Order\Enums\OrderStatus;
use Modules\Order\Models\Order;

class OrderUpdateStatus
{
    /**
     * Create a new event instance.
     */
    public function __construct(
        public Order $order,
        public OrderStatus $status,
        public ?int $reasonId = null,
        public ?int $changedById = null,
        public ?string $note = null,
        public bool $stopUpdateOrderProductStatus = false,
        public ?string $source = null,
    ) {}
}
