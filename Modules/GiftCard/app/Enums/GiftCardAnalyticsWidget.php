<?php

namespace Modules\GiftCard\Enums;

use Modules\Support\Traits\EnumArrayable;

enum GiftCardAnalyticsWidget: string
{
    use EnumArrayable;

    case TotalCards = 'total_cards';
    case ActiveCards = 'active_cards';
    case AssignedCards = 'assigned_cards';
    case TotalTransactions = 'total_transactions';
    case TotalBatches = 'total_batches';
    case ExpiringSoonCards = 'expiring_soon_cards';
    case OutstandingBalance = 'outstanding_balance';
    case SoldValue = 'sold_value';
    case RedeemedValue = 'redeemed_value';
    case RefundedValue = 'refunded_value';
    case BatchFaceValue = 'batch_face_value';
    case CardsHealth = 'cards_health';
    case TransactionMix = 'transaction_mix';
    case BatchActivity = 'batch_activity';
    case BranchHighlights = 'branch_highlights';
    case ExpiryWatch = 'expiry_watch';
    case SalesOverTime = 'sales_over_time';
    case RedemptionOverTime = 'redemption_over_time';
    case SalesByBranch = 'sales_by_branch';
    case LiabilityByCurrency = 'liability_by_currency';
    case UsageByDayOfWeek = 'usage_by_day_of_week';

    public function permission(): string
    {
        return 'admin.gift_cards.analytics';
    }

    /**
     * Get the widgets used by the default analytics layout.
     *
     * @return array<int, string>
     */
    public static function defaultValues(): array
    {
        return [
            self::TotalCards->value,
            self::OutstandingBalance->value,
            self::SoldValue->value,
            self::RedeemedValue->value,
            self::SalesOverTime->value,
            self::TransactionMix->value,
            self::CardsHealth->value,
            self::ExpiryWatch->value,
        ];
    }

    public function isOverviewMetric(): bool
    {
        return in_array($this, [
            self::TotalCards,
            self::ActiveCards,
            self::AssignedCards,
            self::TotalTransactions,
            self::TotalBatches,
            self::ExpiringSoonCards,
            self::OutstandingBalance,
            self::SoldValue,
            self::RedeemedValue,
            self::RefundedValue,
            self::BatchFaceValue,
        ], true);
    }
}
