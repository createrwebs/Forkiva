<?php

namespace Modules\Import\Enums;

use Modules\Support\Traits\EnumArrayable;
use Modules\Support\Traits\EnumTranslatable;

enum ImportMode: string
{
    use EnumArrayable, EnumTranslatable;

    case CreateOnly = 'create_only';
    case UpdateOnly = 'update_only';
    case Upsert = 'upsert';
    case SkipDuplicates = 'skip_duplicates';
    case ReplaceExisting = 'replace_existing';

    /** {@inheritDoc} */
    public static function getTransKey(): string
    {
        return 'import::imports.modes';
    }
}
