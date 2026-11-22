<?php

namespace Modules\Export\Enums;

use Modules\Support\Traits\EnumArrayable;
use Modules\Support\Traits\EnumTranslatable;

enum ExportFormat: string
{
    use EnumArrayable, EnumTranslatable;

    case Xlsx = 'xlsx';
    case Xls = 'xls';
    case Csv = 'csv';
    case Json = 'json';

    /** {@inheritDoc} */
    public static function getTransKey(): string
    {
        return 'support::enums.export_types';
    }
}
