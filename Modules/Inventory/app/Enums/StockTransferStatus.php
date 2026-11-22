<?php

namespace Modules\Inventory\Enums;

use Modules\Support\Traits\EnumArrayable;
use Modules\Support\Traits\EnumTranslatable;

enum StockTransferStatus: string
{
    use EnumArrayable, EnumTranslatable;

    case Draft = 'draft';
    case Approved = 'approved';
    case Dispatched = 'dispatched';
    case PartiallyReceived = 'partially_received';
    case Received = 'received';
    case ClosedWithDiscrepancy = 'closed_with_discrepancy';
    case Cancelled = 'cancelled';
    case Voided = 'voided';

    /**
     * Get the translation key for transfer statuses.
     */
    public static function getTransKey(): string
    {
        return 'inventory::enums.stock_transfer_statuses';
    }

    /**
     * Get the presentation color used by status components.
     */
    public function color(): string
    {
        return match ($this) {
            self::Draft => 'info',
            self::Approved => 'primary',
            self::Dispatched => 'warning',
            self::PartiallyReceived => 'secondary',
            self::Received => 'success',
            self::ClosedWithDiscrepancy,
            self::Cancelled,
            self::Voided => 'error',
        };
    }

    /**
     * Determine whether no further transition is allowed.
     */
    public function isTerminal(): bool
    {
        return in_array($this, [
            self::Received,
            self::ClosedWithDiscrepancy,
            self::Cancelled,
            self::Voided,
        ], true);
    }

    /**
     * Enforce the production workflow transition matrix.
     */
    public function canTransitionTo(self $next): bool
    {
        if ($next === $this) {
            return true;
        }

        return match ($this) {
            self::Draft => in_array($next, [self::Approved, self::Cancelled], true),
            self::Approved => in_array($next, [self::Dispatched, self::Cancelled], true),
            self::Dispatched => in_array($next, [
                self::PartiallyReceived,
                self::Received,
                self::ClosedWithDiscrepancy,
                self::Voided,
            ], true),
            self::PartiallyReceived => in_array($next, [
                self::Received,
                self::ClosedWithDiscrepancy,
            ], true),
            self::Received,
            self::ClosedWithDiscrepancy,
            self::Cancelled,
            self::Voided => false,
        };
    }
}
