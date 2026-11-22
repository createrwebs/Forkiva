<?php

namespace Modules\Setting\Enums;

use Modules\Support\Traits\EnumArrayable;

enum SettingSection: string
{
    use EnumArrayable;

    case General = 'general';
    case Application = 'application';
    case Currency = 'currency';
    case Filesystem = 'filesystem';
    case Pdf = 'pdf';
    case Logo = 'logo';
    case Appearance = 'appearance';
    case Pos = 'pos';
    case Kitchen = 'kitchen';
    case Pwa = 'pwa';
    case Passkeys = 'passkeys';
    case PasswordRecovery = 'password_recovery';
    case TwoFactor = 'two_factor';
    case TableQrOrdering = 'table_qr_ordering';
}
