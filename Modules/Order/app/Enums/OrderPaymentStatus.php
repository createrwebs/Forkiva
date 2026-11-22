<?php

namespace Modules\Order\Enums;

use Modules\Support\Traits\EnumArrayable;
use Modules\Support\Traits\EnumTranslatable;

enum OrderPaymentStatus: string
{
    use EnumArrayable, EnumTranslatable;

    case Unpaid = 'unpaid';
    case Paid = 'paid';
    case PartiallyPaid = 'partially_paid';

    /** {@inheritDoc} */
    public static function getTransKey(): string
    {
        return 'order::enums.order_payment_statuses';
    }

    /**
     * Determine if payment is Partially Paid
     */
    public function isPartiallyPaid(): bool
    {
        return $this === OrderPaymentStatus::PartiallyPaid;
    }

    /**
     * Determine if payment is paid
     */
    public function isPaid(): bool
    {
        return $this === OrderPaymentStatus::Paid;
    }

    /**
     * Determine if payment is Unpaid
     */
    public function isUnpaid(): bool
    {
        return $this === OrderPaymentStatus::Unpaid;
    }
}
