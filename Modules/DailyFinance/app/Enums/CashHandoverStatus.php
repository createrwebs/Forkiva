<?php

namespace Modules\DailyFinance\Enums;

use Modules\Support\Traits\EnumArrayable;
use Modules\Support\Traits\EnumTranslatable;

enum CashHandoverStatus: string
{
    use EnumArrayable, EnumTranslatable;

    case Pending = 'pending';
    case Received = 'received';
    case Cancelled = 'cancelled';

    /** {@inheritDoc} */
    public static function getTransKey(): string
    {
        return 'dailyfinance::cash_handovers.statuses';
    }
}
