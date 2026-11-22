<?php

namespace Modules\Pos\Enums;

use Modules\Support\Traits\EnumArrayable;

enum PosSoundAction: string
{
    use EnumArrayable;

    case Interaction = 'interaction';
    case ItemAdded = 'item_added';
    case ItemUpdated = 'item_updated';
    case ItemRemoved = 'item_removed';
    case OrderSubmitted = 'order_submitted';
    case PaymentCompleted = 'payment_completed';
    case Cancelled = 'cancelled';
    case Error = 'error';

    /**
     * Get default sound
     */
    public function defaultSound(): PosSound
    {
        return match ($this) {
            self::Interaction => PosSound::Tap,
            self::ItemAdded => PosSound::Pop,
            self::ItemUpdated => PosSound::Tick,
            self::ItemRemoved => PosSound::Remove,
            self::OrderSubmitted => PosSound::Success,
            self::PaymentCompleted => PosSound::Cash,
            self::Cancelled => PosSound::Warning,
            self::Error => PosSound::Error,
        };
    }
}
