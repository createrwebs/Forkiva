<?php

namespace Modules\Service\Enums;

use Modules\Support\Traits\EnumArrayable;
use Modules\Support\Traits\EnumTranslatable;

enum PushNotificationProvider: string
{
    use EnumArrayable, EnumTranslatable;

    case Fcm = 'fcm';

    /** {@inheritDoc} */
    public static function getTransKey(): string
    {
        return 'service::enums.push_notification_providers';
    }
}
