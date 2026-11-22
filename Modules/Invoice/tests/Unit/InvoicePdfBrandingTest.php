<?php

namespace Modules\Invoice\Tests\Unit;

use Modules\Invoice\Services\InvoicePDF\InvoicePdfBranding;
use Tests\TestCase;

class InvoicePdfBrandingTest extends TestCase
{
    /**
     * Short brands retain the larger single-line wordmark.
     */
    public function test_short_brand_keeps_its_display_size(): void
    {
        $layout = app(InvoicePdfBranding::class)->layout('Forkiva');

        $this->assertSame(['Forkiva'], $layout['lines']);
        $this->assertSame(21, $layout['font_size']);
    }

    /**
     * Wrapped Arabic stays in logical reading order before each line is shaped.
     */
    public function test_long_arabic_brand_keeps_its_words_in_order(): void
    {
        $name = 'مطاعم ومقاهي فوركيفا للمأكولات العربية';
        $layout = app(InvoicePdfBranding::class)->layout($name);

        $this->assertGreaterThan(1, count($layout['lines']));
        $this->assertSame($name, implode(' ', $layout['lines']));
        $this->assertStringStartsWith('مطاعم', $layout['lines'][0]);
        $this->assertSame(18, $layout['font_size']);
    }

    /**
     * A long unbroken brand is retained without overflowing into the document title.
     */
    public function test_unbroken_brand_splits_without_losing_characters(): void
    {
        $name = str_repeat('W', 200);
        $layout = app(InvoicePdfBranding::class)->layout($name);

        $this->assertGreaterThan(1, count($layout['lines']));
        $this->assertSame($name, implode('', $layout['lines']));
        // A Cairo bold W at 18pt is about 16pt wide; a 240pt line cannot fit 15 of them.
        foreach ($layout['lines'] as $line) {
            $this->assertLessThanOrEqual(14, strlen($line));
        }
    }
}
