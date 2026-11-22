<?php

namespace Modules\Core\Enums;

use Modules\Support\Traits\EnumArrayable;
use Modules\Support\Traits\EnumTranslatable;

enum IdempotencyRecordStatus: string
{
    use EnumArrayable, EnumTranslatable;

    case Processing = 'processing';
    case Completed = 'completed';
    case Failed = 'failed';

    /** {@inheritDoc} */
    public static function getTransKey(): string
    {
        return 'core::enums.idempotency_record_statuses';
    }
}
