<?php

namespace Modules\Pos\Enums;

use Modules\Support\Traits\EnumArrayable;
use Modules\Support\Traits\EnumTranslatable;

enum PosSessionStatus: string
{
    use EnumArrayable, EnumTranslatable;

    case Open = 'open';
    case Closed = 'closed';

    /** {@inheritDoc} */
    public static function getTransKey(): string
    {
        return 'pos::enums.pos_session_statuses';
    }
}
