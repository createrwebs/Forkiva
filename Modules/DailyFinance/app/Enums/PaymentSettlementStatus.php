<?php

namespace Modules\DailyFinance\Enums;

use Modules\Support\Traits\EnumArrayable;
use Modules\Support\Traits\EnumTranslatable;

enum PaymentSettlementStatus: string
{
    use EnumArrayable, EnumTranslatable;

    case Pending = 'pending';
    case Partial = 'partial';
    case Settled = 'settled';
    case Cancelled = 'cancelled';

    /** {@inheritDoc} */
    public static function getTransKey(): string
    {
        return 'dailyfinance::payment_settlements.statuses';
    }
}
