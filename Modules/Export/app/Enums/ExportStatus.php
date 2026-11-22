<?php

namespace Modules\Export\Enums;

use Modules\Support\Traits\EnumArrayable;
use Modules\Support\Traits\EnumTranslatable;

enum ExportStatus: string
{
    use EnumArrayable, EnumTranslatable;

    case Pending = 'pending';
    case Processing = 'processing';
    case Completed = 'completed';
    case Failed = 'failed';

    /** {@inheritDoc} */
    public static function getTransKey(): string
    {
        return 'export::exports.statuses';
    }
}
