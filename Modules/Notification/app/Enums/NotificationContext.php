<?php

namespace Modules\Notification\Enums;

use Modules\Support\Traits\EnumArrayable;
use Modules\Support\Traits\EnumTranslatable;

enum NotificationContext: string
{
    use EnumArrayable, EnumTranslatable;

    case Other = 'other';
    case Driver = 'driver';
    case Employee = 'employee';

    /** {@inheritDoc} */
    public static function getTransKey(): string
    {
        return 'notification::enums.notification_contexts';
    }
}
