<?php

namespace Modules\Notification\Enums;

use Modules\Support\Traits\EnumArrayable;
use Modules\Support\Traits\EnumTranslatable;

enum NotificationPushTokenClient: string
{
    use EnumArrayable, EnumTranslatable;

    case Web = 'web';
    case EmployeeApp = 'employee_app';
    case DriverApp = 'driver_app';

    /** {@inheritDoc} */
    public static function getTransKey(): string
    {
        return 'notification::enums.notification_push_token_clients';
    }
}
