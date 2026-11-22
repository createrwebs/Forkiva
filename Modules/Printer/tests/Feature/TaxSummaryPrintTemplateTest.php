<?php

namespace Modules\Printer\Tests\Feature;

use Modules\Printer\Enum\PrintContentType;
use Modules\Printer\Enum\PrinterPaperSize;
use Modules\Printer\Services\Render\PrintRenderServiceInterface;
use Tests\TestCase;

class TaxSummaryPrintTemplateTest extends TestCase
{
    public function test_financial_print_templates_render_product_and_global_tax_breakdowns(): void
    {
        $payload = $this->payload();
        $renderer = app(PrintRenderServiceInterface::class);

        foreach ([
            PrintContentType::Bill,
            PrintContentType::Waiter,
            PrintContentType::Delivery,
            PrintContentType::Invoice,
        ] as $type) {
            $html = $renderer->renderToHtml($type, $payload, PrinterPaperSize::Paper80mm);

            $this->assertStringContainsString('Product VAT', $html, $type->value);
            $this->assertStringContainsString('Product tax', $html, $type->value);
            $this->assertStringContainsString('Included in price', $html, $type->value);
            $this->assertStringContainsString('Global VAT', $html, $type->value);
            $this->assertStringContainsString('Global tax', $html, $type->value);
            $this->assertStringContainsString('Added to price', $html, $type->value);
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function payload(): array
    {
        return [
            'order' => [
                'order_number' => 'TEST-1',
                'subtotal' => 100,
                'total' => 130,
                'due_amount' => 130,
            ],
            'branch' => ['name' => 'Test Branch'],
            'customer' => ['name' => 'Test Customer'],
            'seller' => ['legal_name' => 'Test Branch'],
            'buyer' => ['legal_name' => 'Test Customer'],
            'invoice_number' => 'INV-1',
            'subtotal' => 100,
            'total' => 130,
            'paid_amount' => 0,
            'refunded_amount' => 0,
            'net_paid' => 0,
            'currency_subunit' => 2,
            'products' => [],
            'lines' => [],
            'discounts' => [],
            'allocations' => [],
            'payments' => [],
            'charges' => [],
            'tax_summary' => [
                [
                    'key' => 'product:1',
                    'name' => 'Product VAT',
                    'rate' => 15,
                    'type' => 'inclusive',
                    'scope' => 'product',
                    'amount' => 15,
                ],
                [
                    'key' => 'global:2',
                    'name' => 'Global VAT',
                    'rate' => 15,
                    'type' => 'exclusive',
                    'scope' => 'global',
                    'amount' => 15,
                ],
            ],
        ];
    }
}
