<?php

namespace Modules\Reservation\Enums;

use Modules\Support\Traits\EnumArrayable;
use Modules\Support\Traits\EnumTranslatable;

enum ReservationReminderStatus: string
{
    use EnumArrayable, EnumTranslatable;

    case Pending = 'pending';
    case Sent = 'sent';
    case Failed = 'failed';
    case Cancelled = 'cancelled';

    /** {@inheritDoc} */
    public static function getTransKey(): string
    {
        return 'reservation::enums.reservation_reminder_statuses';
    }
}
