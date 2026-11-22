<?php

namespace Modules\Invoice\Tests\Unit;

use Tests\TestCase;

class InvoicePdfTemplateTest extends TestCase
{
    public function test_invoice_template_uses_embedded_cairo_and_portable_print_layouts(): void
    {
        $template = file_get_contents(module_path('Pdf', 'resources/views/invoices/pdf.blade.php'));

        $this->assertIsString($template);
        $this->assertStringContainsString("font-family: 'Cairo'", $template);
        $this->assertStringContainsString("storage_path('fonts/Cairo-Regular.ttf')", $template);
        $this->assertStringContainsString("storage_path('fonts/Cairo-Bold.ttf')", $template);
        $this->assertStringContainsString('display: table-header-group', $template);
        $this->assertStringContainsString('page-break-inside: avoid', $template);
        $this->assertStringNotContainsString('display: flex', $template);
        $this->assertStringNotContainsString('display: grid', $template);
        $this->assertStringNotContainsString('<script', $template);
        $this->assertStringNotContainsString('fonts.googleapis.com', $template);
    }
}
