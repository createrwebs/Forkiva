<?php

namespace Modules\Translation\Enums;

use Modules\Support\Traits\EnumArrayable;

enum TranslationImportMode: string
{
    use EnumArrayable;

    case FillMissing = 'fill_missing';
    case Overwrite = 'overwrite';
}
