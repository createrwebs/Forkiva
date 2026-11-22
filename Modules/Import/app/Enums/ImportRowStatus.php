<?php

namespace Modules\Import\Enums;

use Modules\Support\Traits\EnumArrayable;
use Modules\Support\Traits\EnumTranslatable;

enum ImportRowStatus: string
{
    use EnumArrayable, EnumTranslatable;

    case Pending = 'pending';
    case Valid = 'valid';
    case Invalid = 'invalid';
    case Processed = 'processed';
    case Skipped = 'skipped';
    case Failed = 'failed';

    /** {@inheritDoc} */
    public static function getTransKey(): string
    {
        return 'import::imports.row_statuses';
    }
}
