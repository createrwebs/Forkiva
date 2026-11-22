<?php

namespace Modules\User\Enums;

use Modules\Support\Traits\EnumArrayable;

enum TwoFactorMethod: string
{
    use EnumArrayable;

    case Authenticator = 'authenticator';
    case Email = 'email';
    case Sms = 'sms';
}
