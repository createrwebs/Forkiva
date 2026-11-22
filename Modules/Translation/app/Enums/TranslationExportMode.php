<?php

namespace Modules\Translation\Enums;

use Modules\Support\Traits\EnumArrayable;

enum TranslationExportMode: string
{
    use EnumArrayable;

    case Values = 'values';
    case EmptyAll = 'empty_all';
    case EmptyMissing = 'empty_missing';
}
