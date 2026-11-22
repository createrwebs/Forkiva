<?php

namespace Modules\Order\Enums;

use Modules\Support\Traits\EnumArrayable;
use Modules\Support\Traits\EnumTranslatable;

enum OrderSource: string
{
    use EnumArrayable, EnumTranslatable;

    case TableQr = 'table_qr';

    /** {@inheritDoc} */
    public static function getTransKey(): string
    {
        return 'order::enums.order_sources';
    }

    /**
     * Get source icon.
     */
    public function icon(): string
    {
        return match ($this) {
            self::TableQr => 'tabler-qrcode',
        };
    }

    /**
     * Get source color.
     */
    public function color(): string
    {
        return match ($this) {
            self::TableQr => 'info',
        };
    }
}
