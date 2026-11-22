<?php

namespace Modules\Notification\Enums;

use Modules\Support\Traits\EnumArrayable;
use Modules\Support\Traits\EnumTranslatable;

enum NotificationTemplateContentType: string
{
    use EnumArrayable, EnumTranslatable;

    case Text = 'text';
    case Markdown = 'markdown';
    case Html = 'html';

    public static function getTransKey(): string
    {
        return 'notification::notification_templates.content_type';
    }
}
