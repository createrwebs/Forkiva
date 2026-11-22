<?php

namespace Modules\Printer\Tests\Feature;

use DOMDocument;
use DOMElement;
use Dompdf\Dompdf;
use Dompdf\Frame;
use DOMXPath;
use Modules\Pdf\Enums\PdfDriver;
use Modules\Pdf\Services\Rendering\ArabicHtmlProcessor;
use Modules\Printer\Enum\PrintContentType;
use Modules\Printer\Enum\PrinterPaperSize;
use Modules\Printer\Services\Render\PrintRenderService;
use Modules\Printer\Services\Render\ReceiptPaperHeight;
use Modules\Printer\Tests\Fixtures\OrderPrintFixture;
use Modules\Setting\Repositories\SettingRepository;
use Tests\TestCase;

class DompdfReceiptArabicTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        app()->instance('setting', new SettingRepository(collect([
            'default_locale' => ['payload' => serialize('ar'), 'is_encryptable' => false, 'is_translatable' => false],
        ])));
    }

    public function test_all_receipts_wrap_arabic_in_logical_order_and_render_every_shaped_line(): void
    {
        foreach (PrinterPaperSize::cases() as $size) {
            foreach (PrintContentType::cases() as $type) {
                $payload = OrderPrintFixture::make(1, true);
                $view = app(PrintRenderService::class)->build($type, $payload, $size)->with('pdfDriver', PdfDriver::Dompdf);
                $html = app(ArabicHtmlProcessor::class)->process($view->render());
                $document = new DOMDocument;
                $document->loadHTML('<?xml encoding="UTF-8">'.$html, LIBXML_NOERROR | LIBXML_NOWARNING);
                $xpath = new DOMXPath($document);
                $name = $xpath->query('//*[contains(concat(" ", @class, " "), " item-name ") or contains(concat(" ", @class, " "), " kitchen-name ")]')->item(0);
                $logicalLines = [];
                foreach ($xpath->query('.//*[@data-dompdf-line]', $name) as $line) {
                    $logicalLines[] = $line->getAttribute('data-dompdf-line');
                }
                $this->assertGreaterThan(1, count($logicalLines), $type->value.' '.$size->value);
                $this->assertSame($payload['products'][0]['name'], implode(' ', $logicalLines));
                $this->assertStringStartsWith('ساندويش', $logicalLines[0]);
                $expected = [];
                foreach ($xpath->query('//*[@data-dompdf-line]') as $line) {
                    if (trim($line->textContent) !== '') {
                        $expected[] = trim($line->textContent);
                    }
                }

                $rendered = [];
                $dompdf = new Dompdf;
                $dompdf->loadHtml($html);
                $dompdf->setPaper([0, 0, $size->getAsNumber() * 72 / 25.4, 2000 * 72 / 25.4]);
                $dompdf->setCallbacks([[
                    'event' => 'end_frame',
                    'f' => static function (Frame $frame) use (&$rendered): void {
                        $node = $frame->get_node();
                        if ($frame->is_text_node() && $node->parentNode instanceof DOMElement
                            && $node->parentNode->hasAttribute('data-dompdf-line')) {
                            $rendered[] = trim($node->textContent);
                        }
                    },
                ]]);
                $dompdf->render();
                // A PDF header/page count alone can pass even for a completely blank receipt.
                $this->assertNotEmpty($rendered);
                $this->assertSame($expected, $rendered, $type->value.' '.$size->value);
            }
        }
    }

    public function test_wrapping_preserves_decimals_escaped_text_and_explicit_line_breaks(): void
    {
        $html = '<html><head><meta charset="UTF-8"><style>body {font-family: DejaVu Sans; font-size: 8pt} p {width: 100pt}</style></head><body data-dompdf-wrap-width-mm="58">'
            .'<p>الضريبة 0.300 والنسبة 5.25% والكمية 1.5</p>'
            .'<p>السطر الأول<br>السطر الثاني</p>'
            .'<p>ملاحظة &lt;img src=x&gt; &amp; تعليمات</p></body></html>';
        $processed = app(ArabicHtmlProcessor::class)->process($html);
        $document = new DOMDocument;
        $document->loadHTML('<?xml encoding="UTF-8">'.$processed, LIBXML_NOERROR | LIBXML_NOWARNING);
        $xpath = new DOMXPath($document);
        $paragraphs = $document->getElementsByTagName('p');
        foreach (['0.300', '5.25%', '1.5'] as $value) {
            $this->assertStringContainsString($value, $paragraphs->item(0)->textContent);
        }
        $lines = $xpath->query('.//*[@data-dompdf-line]', $paragraphs->item(1));
        $this->assertSame('السطر الأول', $lines->item(0)->getAttribute('data-dompdf-line'));
        $this->assertSame('السطر الثاني', $lines->item(1)->getAttribute('data-dompdf-line'));
        $this->assertSame(0, $document->getElementsByTagName('img')->length);
        $this->assertStringContainsString('&', $paragraphs->item(2)->textContent);
    }

    public function test_long_arabic_bills_allow_room_for_flowing_text_as_well_as_prewrapped_lines(): void
    {
        $view = app(PrintRenderService::class)->build(
            PrintContentType::Bill, OrderPrintFixture::make(45, true), PrinterPaperSize::Paper80mm,
        );
        $height = app(ReceiptPaperHeight::class)->measure($view, 80);
        $this->assertLessThan(ReceiptPaperHeight::MAX_HEIGHT_MM, $height);
        $html = $view->with('pdfDriver', PdfDriver::Dompdf)->render();
        $html = str_replace('data-dompdf-wrap-width-mm', 'data-flow-width-mm', $html);
        $dompdf = new Dompdf;
        $dompdf->loadHtml(app(ArabicHtmlProcessor::class)->process($html));
        $dompdf->setPaper([0, 0, 80 * 72 / 25.4, $height * 72 / 25.4]);
        $dompdf->render();
        $this->assertSame(1, $dompdf->getCanvas()->get_page_count());
    }
}
