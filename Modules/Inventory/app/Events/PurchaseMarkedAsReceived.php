<?php

namespace Modules\Inventory\Events;

use Modules\Inventory\Models\Purchase;
use Modules\Inventory\Models\PurchaseReceipt;

class PurchaseMarkedAsReceived
{
    /**
     * Create a new event instance.
     */
    public function __construct(
        public Purchase $purchase,
        public ?PurchaseReceipt $receipt = null,
    ) {}
}
