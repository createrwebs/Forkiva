<?php

namespace Modules\Pdf\Enums;

enum PdfDriver: string
{
    case Dompdf = 'dompdf';
    case Browsershot = 'browsershot';
    case Chrome = 'chrome';
    case Cloudflare = 'cloudflare';
    case Gotenberg = 'gotenberg';
    case Weasyprint = 'weasyprint';

    /**
     * Get all supported driver values.
     *
     * @return list<string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
