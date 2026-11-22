<?php

namespace Modules\Order\Enums;

use Modules\Support\Traits\EnumArrayable;
use Modules\Support\Traits\EnumTranslatable;

enum OrderChargeCode: string
{
    use EnumArrayable, EnumTranslatable;

    case DeliveryFee = 'delivery_fee';
    case SmallOrderSurcharge = 'small_order_surcharge';
    case PeakTimeSurcharge = 'peak_time_surcharge';
    case Tip = 'tip';
    case ServiceCharge = 'service_charge';

    public static function getTransKey(): string
    {
        return 'order::enums.order_charge_codes';
    }
}
