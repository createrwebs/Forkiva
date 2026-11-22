<?php

namespace Modules\Pos\Enums;

use Modules\Support\Traits\EnumArrayable;

enum PosSound: string
{
    use EnumArrayable;

    case Tap = 'tap';
    case Pop = 'pop';
    case Tick = 'tick';
    case Remove = 'remove';
    case Open = 'open';
    case Success = 'success';
    case Cash = 'cash';
    case Warning = 'warning';
    case Error = 'error';
}
