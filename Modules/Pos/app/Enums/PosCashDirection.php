<?php

namespace Modules\Pos\Enums;

use Modules\Support\Traits\EnumArrayable;
use Modules\Support\Traits\EnumTranslatable;

enum PosCashDirection: string
{
    use EnumArrayable, EnumTranslatable;

    case In = 'in';     // money added to the drawer
    case Out = 'out';    // money removed from the drawer
    case Adjust = 'adjust'; // correction without physical add/remove (e.g., recount fix)

    /**
     * Get enum Arrayable as translation
     */
    public static function toArrayTrans(array $except = []): array
    {
        $cases = [];

        foreach (self::values() as $value) {
            $direction = PosCashDirection::from($value);
            if (in_array($value, $except)) {
                continue;
            }
            $cases[] = [
                'id' => $value,
                'name' => __(PosCashDirection::getTransKey().".$value"),
                'icon' => $direction->icon(),
                'color' => $direction->color(),
            ];
        }

        return $cases;
    }

    /** {@inheritDoc} */
    public static function getTransKey(): string
    {
        return 'pos::enums.pos_cash_directions';
    }

    /**
     * Get icon for direction
     */
    public function icon(): string
    {
        return match ($this) {
            self::In => 'tabler-arrow-up-circle',
            self::Out => 'tabler-arrow-down-circle',
            self::Adjust => 'tabler-adjustments',
        };
    }

    /**
     * Get color for direction
     */
    public function color(): string
    {
        return match ($this) {
            self::In => '#2ECC71',
            self::Out => '#E74C3C',
            self::Adjust => '#F1C40F',
        };
    }

    /**
     * Get enum as array value with trans
     */
    public function toTrans(): array
    {
        return [
            'id' => $this->value,
            'name' => $this->trans(),
            'icon' => $this->icon(),
            'color' => $this->color(),
        ];
    }
}
