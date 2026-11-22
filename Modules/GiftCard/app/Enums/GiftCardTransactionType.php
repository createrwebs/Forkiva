<?php

namespace Modules\GiftCard\Enums;

use Modules\Support\Traits\EnumArrayable;
use Modules\Support\Traits\EnumTranslatable;

enum GiftCardTransactionType: string
{
    use EnumArrayable, EnumTranslatable;

    case Purchase = 'purchase';
    case Redeem = 'redeem';
    case Refund = 'refund';
    case Recharge = 'recharge';
    case Adjustment = 'adjustment';
    case Expire = 'expire';
    case CashOut = 'cash_out';
    case IssueVoid = 'issue_void';
    case AdjustmentDecrease = 'adjustment_decrease';
    case Breakage = 'breakage';
    case Reversal = 'reversal';

    public static function getTransKey(): string
    {
        return 'giftcard::enums.transaction_types';
    }

    /**
     * Get the semantic table color for this transaction's balance effect.
     */
    public function color(): string
    {
        return match ($this) {
            self::Purchase, self::Recharge => 'success',
            self::Redeem => 'primary',
            self::Refund, self::Adjustment, self::Reversal => 'info',
            self::Expire, self::AdjustmentDecrease, self::Breakage => 'warning',
            self::CashOut, self::IssueVoid => 'error',
        };
    }

    /**
     * Get the direction in which this type changes the available card balance.
     */
    public function affectsBalanceBy(): int
    {
        return match ($this) {
            self::Purchase, self::Refund, self::Recharge, self::Adjustment => 1,
            self::Redeem, self::CashOut, self::IssueVoid, self::AdjustmentDecrease, self::Breakage => -1,
            self::Expire, self::Reversal => 0,
        };
    }
}
