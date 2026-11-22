<?php

namespace Modules\Translation\Enums;

use Modules\Support\Traits\EnumArrayable;

enum TranslationIndexStatus: string
{
    use EnumArrayable;

    case All = 'all';
    case Missing = 'missing';
    case Filled = 'filled';
}
