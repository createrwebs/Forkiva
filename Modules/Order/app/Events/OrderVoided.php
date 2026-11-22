<?php

namespace Modules\Order\Events;

use Modules\Order\Enums\OrderStatus;
use Modules\Order\Models\Order;
use Modules\Payment\Enums\RefundPaymentMethod;
use Modules\Pos\Models\PosSession;

class OrderVoided
{
    /**
     * Create a new event instance.
     */
    public function __construct(
        public Order $order,
        public OrderStatus $status,
        public ?int $reasonId = null,
        public ?int $changedById = null,
        public ?RefundPaymentMethod $refundPaymentMethod = null,
        public ?PosSession $posSession = null,
        public ?string $note = null,
        public ?string $giftCardCode = null,
    ) {}
}
