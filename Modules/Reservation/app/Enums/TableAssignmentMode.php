<?php

namespace Modules\Reservation\Enums;

use Modules\Support\Traits\EnumArrayable;
use Modules\Support\Traits\EnumTranslatable;

enum TableAssignmentMode: string
{
    use EnumArrayable, EnumTranslatable;

    case AssignLater = 'assign_later';
    case AssignNow = 'assign_now';
    case AutoAssign = 'auto_assign';

    /** {@inheritDoc} */
    public static function getTransKey(): string
    {
        return 'reservation::enums.table_assignment_modes';
    }

    /**
     * Get UI color.
     */
    public function color(): string
    {
        return match ($this) {
            self::AssignLater => 'warning',
            self::AssignNow => 'primary',
            self::AutoAssign => 'info',
        };
    }
}
