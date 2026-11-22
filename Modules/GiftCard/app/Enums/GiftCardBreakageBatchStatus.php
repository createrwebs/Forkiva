<?php

namespace Modules\GiftCard\Enums;

use Modules\Support\Traits\EnumArrayable;
use Modules\Support\Traits\EnumTranslatable;

enum GiftCardBreakageBatchStatus: string
{
    use EnumArrayable, EnumTranslatable;

    case Draft = 'draft';
    case Reviewed = 'reviewed';
    case Posted = 'posted';
    case Reversed = 'reversed';

    /**
     * Get the translation key for breakage batch statuses.
     */
    public static function getTransKey(): string
    {
        return 'giftcard::enums.breakage_batch_statuses';
    }

    /**
     * Get the presentation color used by status components.
     */
    public function color(): string
    {
        return match ($this) {
            self::Draft,
            self::Reviewed => 'info',
            self::Posted => 'success',
            self::Reversed => 'warning',
        };
    }
}
