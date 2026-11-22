<?php

namespace Modules\Printer\Services\Render;

use DOMElement;
use Dompdf\Dompdf;
use Dompdf\Frame;
use Dompdf\Options;
use Illuminate\View\View;
use Modules\Pdf\Enums\PdfDriver;
use Modules\Pdf\Services\Rendering\ArabicHtmlProcessor;

class ReceiptPaperHeight
{
    public const MAX_HEIGHT_MM = 2000;

    public function __construct(private ArabicHtmlProcessor $arabicHtmlProcessor) {}

    /**
     * Measure the shared receipt layout locally so every driver can use a roll-sized page.
     * Very long orders paginate at the cap rather than exceeding printer/PDF size limits.
     */
    public function measure(View $view, float $paperWidthMm): float
    {
        $html = view($view->name(), [...$view->getData(), 'pdfDriver' => PdfDriver::Dompdf])->render();
        $layouts = [$html];
        if (preg_match('/\p{Arabic}/u', $html) === 1) {
            // Native browser/Pango line breaks can use more space than Dompdf's
            // prewrapped Arabic lines, especially in captions with numeric spans.
            $layouts[] = str_replace('data-dompdf-wrap-width-mm', 'data-flow-width-mm', $html);
        }
        $heightPt = 0;
        foreach ($layouts as $layout) {
            $options = new Options;
            $options->setIsRemoteEnabled(false);
            $dompdf = new Dompdf($options);
            $dompdf->loadHtml($this->arabicHtmlProcessor->process($layout));
            $dompdf->setPaper([0, 0, $paperWidthMm * 72 / 25.4, self::MAX_HEIGHT_MM * 72 / 25.4]);
            $dompdf->setCallbacks([[
                'event' => 'end_frame',
                'f' => static function (Frame $frame) use (&$heightPt): void {
                    $node = $frame->get_node();
                    if ($node instanceof DOMElement && in_array('receipt', explode(' ', $node->getAttribute('class')), true)) {
                        $box = $frame->get_border_box();
                        $heightPt = max($heightPt, $box['y'] + $box['h']);
                    }
                },
            ]]);
            $dompdf->render();

            if ($dompdf->getCanvas()->get_page_count() > 1) {
                return self::MAX_HEIGHT_MM;
            }
        }

        if ($heightPt <= 0) {
            return self::MAX_HEIGHT_MM;
        }

        // Allow for small font/layout differences between PDF engines and cutter feed.
        return min(self::MAX_HEIGHT_MM, max(40, ceil($heightPt * 25.4 / 72 * 1.04 + 4)));
    }
}
