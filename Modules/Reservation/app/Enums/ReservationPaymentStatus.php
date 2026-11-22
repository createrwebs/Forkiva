<?php

namespace Modules\Reservation\Enums;

use Modules\Support\Traits\EnumArrayable;
use Modules\Support\Traits\EnumTranslatable;

enum ReservationPaymentStatus: string
{
    use EnumArrayable, EnumTranslatable;

    case NotRequired = 'not_required';
    case Unpaid = 'unpaid';
    case PartiallyPaid = 'partially_paid';
    case Paid = 'paid';
    case Refunded = 'refunded';
    case Failed = 'failed';

    /** {@inheritDoc} */
    public static function getTransKey(): string
    {
        return 'reservation::enums.reservation_payment_statuses';
    }

    /**
     * Get UI color.
     */
    public function color(): string
    {
        return match ($this) {
            self::NotRequired => 'secondary',
            self::Unpaid => 'warning',
            self::PartiallyPaid => 'info',
            self::Paid => 'success',
            self::Refunded => '#8B5CF6',
            self::Failed => 'error',
        };
    }
}
