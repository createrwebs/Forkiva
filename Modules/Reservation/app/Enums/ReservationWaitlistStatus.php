<?php

namespace Modules\Reservation\Enums;

use Modules\Support\Traits\EnumArrayable;
use Modules\Support\Traits\EnumTranslatable;

enum ReservationWaitlistStatus: string
{
    use EnumArrayable, EnumTranslatable;

    case Waiting = 'waiting';
    case Notified = 'notified';
    case Converted = 'converted';
    case Cancelled = 'cancelled';
    case Expired = 'expired';

    /** {@inheritDoc} */
    public static function getTransKey(): string
    {
        return 'reservation::enums.reservation_waitlist_statuses';
    }

    /**
     * Get UI color.
     */
    public function color(): string
    {
        return match ($this) {
            self::Waiting => '#F59E0B',
            self::Notified => '#2563EB',
            self::Converted => '#16A34A',
            self::Cancelled => '#DC2626',
            self::Expired => '#475569',
        };
    }
}
