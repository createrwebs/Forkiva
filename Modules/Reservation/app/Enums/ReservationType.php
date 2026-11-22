<?php

namespace Modules\Reservation\Enums;

use Modules\Support\Traits\EnumArrayable;
use Modules\Support\Traits\EnumTranslatable;

enum ReservationType: string
{
    use EnumArrayable, EnumTranslatable;

    case Standard = 'standard';
    case WalkIn = 'walk_in';
    case LargeParty = 'large_party';
    case PrivateRoom = 'private_room';
    case Event = 'event';
    case Vip = 'vip';

    /** {@inheritDoc} */
    public static function getTransKey(): string
    {
        return 'reservation::enums.reservation_types';
    }

    /**
     * Get UI color.
     */
    public function color(): string
    {
        return match ($this) {
            self::Standard => 'primary',
            self::WalkIn => 'info',
            self::LargeParty => 'warning',
            self::PrivateRoom => 'secondary',
            self::Event => '#7C3AED',
            self::Vip => '#F59E0B',
        };
    }
}
