<?php

namespace Modules\Reservation\Enums;

use Modules\Support\Traits\EnumArrayable;
use Modules\Support\Traits\EnumTranslatable;

enum ReservationDepositTransactionType: string
{
    use EnumArrayable, EnumTranslatable;

    case Opening = 'opening';
    case Collection = 'collection';
    case Application = 'application';
    case Refund = 'refund';
    case Forfeiture = 'forfeiture';
    case Reversal = 'reversal';

    /**
     * Get the translation namespace for deposit transaction type labels.
     */
    public static function getTransKey(): string
    {
        return 'reservation::enums.deposit_transaction_types';
    }

    /**
     * Get the signed effect this transaction type has on the held deposit balance.
     */
    public function balanceDirection(): int
    {
        return match ($this) {
            self::Opening, self::Collection => 1,
            self::Application, self::Refund, self::Forfeiture => -1,
            self::Reversal => 0,
        };
    }
}
