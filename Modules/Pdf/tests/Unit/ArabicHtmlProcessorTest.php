<?php

namespace Modules\Pdf\Tests\Unit;

use Modules\Pdf\Services\Rendering\ArabicHtmlProcessor;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class ArabicHtmlProcessorTest extends TestCase
{
    #[Test]
    public function it_preserves_html_without_arabic_text(): void
    {
        $html = '<p>English text 123</p>';

        $this->assertSame($html, (new ArabicHtmlProcessor)->process($html));
    }

    #[Test]
    public function it_shapes_arabic_text_without_changing_markup_or_numbers(): void
    {
        $html = '<p class="title">Payroll / تدقيق الرواتب 123</p>';
        $processed = (new ArabicHtmlProcessor)->process($html);

        $this->assertStringStartsWith('<p class="title">Payroll / ', $processed);
        $this->assertStringContainsString('123', $processed);
        $this->assertStringEndsWith('</p>', $processed);
        $this->assertStringNotContainsString('تدقيق الرواتب', $processed);
        $this->assertMatchesRegularExpression('/[\x{FB50}-\x{FDFF}\x{FE70}-\x{FEFF}]/u', $processed);
    }

    #[Test]
    public function it_wraps_a4_arabic_in_reading_order_using_the_requested_margins(): void
    {
        $text = 'تكلفة صاحب العمل والراتب الأساسي والضريبة 1234.567 والاستقطاعات';
        $html = '<html><head><meta charset="UTF-8"><style>body { margin: 0; font-family: DejaVu Sans; font-size: 16pt } table { width: 100%; table-layout: fixed } td { width: 50% }</style></head><body><table><tr><td>'.$text.'</td><td>Reference</td></tr></table></body></html>';
        $lineCounts = [];
        foreach ([10, 40] as $margin) {
            $processed = (new ArabicHtmlProcessor)->process($html, 210, [
                'top' => $margin, 'right' => $margin, 'bottom' => $margin, 'left' => $margin, 'unit' => 'mm',
            ]);
            $document = new \DOMDocument;
            $document->loadHTML('<?xml encoding="UTF-8">'.$processed, LIBXML_NONET | LIBXML_NOERROR | LIBXML_NOWARNING);
            $lines = (new \DOMXPath($document))->query('//*[@data-dompdf-line]');
            $logicalLines = [];
            foreach ($lines as $line) {
                $logicalLines[] = $line->getAttribute('data-dompdf-line');
            }
            $this->assertSame($text, implode(' ', $logicalLines));
            $this->assertStringStartsWith('تكلفة', $logicalLines[0]);
            $this->assertStringContainsString('1234.567', $document->textContent);
            $lineCounts[] = count($logicalLines);
        }
        $this->assertGreaterThan($lineCounts[0], $lineCounts[1]);
    }
}
