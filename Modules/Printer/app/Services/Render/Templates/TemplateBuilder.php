<?php

namespace Modules\Printer\Services\Render\Templates;

use Illuminate\View\View;
use Modules\Printer\Enum\PrintContentType;
use Modules\Printer\Enum\PrinterPaperSize;

class TemplateBuilder
{
    /**
     * Build template
     */
    public function build(PrintContentType $type, array $payload, PrinterPaperSize $paperSize): View
    {
        $profile = config('printer.media_profiles.'.$paperSize->value);

        return view($this->getTemplateView($type), compact('payload', 'profile'));
    }

    /**
     * Get template view
     */
    public function getTemplateView(PrintContentType $type): string
    {
        return 'pdf::print.templates.'.$type->value;
    }
}
