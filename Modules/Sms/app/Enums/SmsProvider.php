<?php

namespace Modules\Sms\Enums;

use Modules\Support\Traits\EnumArrayable;
use Modules\Support\Traits\EnumTranslatable;

enum SmsProvider: string
{
    use EnumArrayable, EnumTranslatable;

    case Log = 'log';
    case Array = 'array';
    case JawalSms = 'jawal_sms';
    case Taqnyat = 'taqnyat';
    case Nexmo = 'nexmo';
    case Twilio = 'twilio';
    case MoraSa = 'morasa';
    case Msegat = 'msegat';
    case Kobikom = 'kobikom';
    case Unifonic = 'unifonic';
    case JorMall = 'jor_mall';
    case ThaiBulkSms = 'thaibulksms';
    case SmsMkt = 'smsmkt';

    /** {@inheritDoc} */
    public static function getTransKey(): string
    {
        return 'sms::enums.providers';
    }
}
