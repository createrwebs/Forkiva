<?php

namespace Modules\Import\Enums;

use Modules\Support\Traits\EnumArrayable;
use Modules\Support\Traits\EnumTranslatable;

enum ImportOperation: string
{
    use EnumArrayable, EnumTranslatable;

    case Create = 'create';
    case Update = 'update';
    case Skip = 'skip';
    case Invalid = 'invalid';

    /** {@inheritDoc} */
    public static function getTransKey(): string
    {
        return 'import::imports.operations';
    }
}
