<?php

namespace Modules\Reservation\Enums;

use Modules\Support\Traits\EnumArrayable;
use Modules\Support\Traits\EnumTranslatable;

enum TableAssignmentStatus: string
{
    use EnumArrayable, EnumTranslatable;

    case Unassigned = 'unassigned';
    case Assigned = 'assigned';
    case PartiallyAssigned = 'partially_assigned';
    case NotRequired = 'not_required';

    /** {@inheritDoc} */
    public static function getTransKey(): string
    {
        return 'reservation::enums.table_assignment_statuses';
    }

    /**
     * Get enum as translated array value.
     *
     * @return array<string, mixed>
     */
    public function toTrans(): array
    {
        return [
            'id' => $this->value,
            'name' => $this->trans(),
            'color' => $this->color(),
        ];
    }

    /**
     * Get UI color.
     */
    public function color(): string
    {
        return match ($this) {
            self::Unassigned => '#F59E0B',
            self::Assigned => '#16A34A',
            self::PartiallyAssigned => '#2563EB',
            self::NotRequired => '#6B7280',
        };
    }
}
