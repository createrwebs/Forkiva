<?php

namespace Modules\Order\Events;

use Modules\SeatingPlan\Models\TableMerge;

class OrderMergeBillingPaid
{
    /**
     * Create a new event instance.
     */
    public function __construct(public TableMerge $tableMerge) {}
}
