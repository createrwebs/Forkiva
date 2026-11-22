<?php

namespace Modules\GiftCard\Enums;

use Modules\Support\Traits\EnumArrayable;
use Modules\Support\Traits\EnumTranslatable;

enum GiftCardFundingType: string
{
    use EnumArrayable, EnumTranslatable;

    case Paid = 'paid';
    case Promotional = 'promotional';

    /** {@inheritDoc} */
    public static function getTransKey(): string
    {
        return 'giftcard::enums.funding_types';
    }
}
