<?php

namespace Modules\Printer\Tests\Feature;

use Illuminate\Support\Facades\Storage;
use Mockery;
use Modules\Invoice\Enums\InvoicePartyType;
use Modules\Invoice\Enums\InvoiceType;
use Modules\Invoice\Models\Invoice;
use Modules\Invoice\Models\InvoiceParty;
use Modules\Order\Enums\OrderType;
use Modules\Order\Models\Order;
use Modules\Order\Models\OrderCharge;
use Modules\Pdf\Data\PdfRenderRequest;
use Modules\Pdf\Enums\PdfDocumentType;
use Modules\Pdf\Enums\PdfDriver;
use Modules\Pdf\Services\Generator\PdfGeneratorInterface;
use Modules\Printer\Enum\PrintContentType;
use Modules\Printer\Enum\PrinterPaperSize;
use Modules\Printer\Factories\PrintContents\PrintInvoiceContentFactory;
use Modules\Printer\Factories\PrintContents\PrintWaiterContentFactory;
use Modules\Printer\Services\Render\PrintRenderService;
use Modules\Printer\Services\Render\ReceiptPaperHeight;
use Modules\Printer\Tests\Fixtures\OrderPrintFixture;
use Modules\Setting\Repositories\SettingRepository;
use Tests\TestCase;

class OrderPrintLayoutTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->printerLocale('en');
    }

    public function test_all_receipt_templates_use_the_printer_language_and_portable_layout_for_every_driver(): void
    {
        $renderer = app(PrintRenderService::class);
        foreach (['en', 'ar'] as $locale) {
            $this->printerLocale($locale);
            // The UI locale must not override the printer's configured language.
            app()->setLocale($locale === 'ar' ? 'en' : 'ar');
            foreach (PrinterPaperSize::cases() as $size) {
                foreach (PrintContentType::cases() as $type) {
                    foreach (PdfDriver::cases() as $driver) {
                        $view = $renderer->build($type, OrderPrintFixture::make(1, $locale === 'ar'), $size);
                        $html = view($view->name(), [...$view->getData(), 'pdfDriver' => $driver])->render();
                        $this->assertStringContainsString('dir="'.($locale === 'ar' ? 'rtl' : 'ltr').'"', $html);
                        $this->assertStringContainsString('data:font/truetype;base64,', $html);
                        $this->assertStringNotContainsString('var(--', $html);
                        $this->assertStringNotContainsString('display: grid', $html);
                        $this->assertStringNotContainsString('display: flex', $html);
                        $this->assertStringNotContainsString('printer::thermal_print.', $html);
                        $this->assertStringContainsString('ITEM-1', $html);
                        if ($type === PrintContentType::Kitchen) {
                            $this->assertStringContainsString('x1.5', strip_tags($html));
                        }
                    }
                }
            }
        }
    }

    public function test_pdf_paper_width_matches_the_printer_and_height_follows_the_receipt(): void
    {
        $heights = [];
        $generator = Mockery::mock(PdfGeneratorInterface::class);
        $generator->shouldReceive('generate')->times(4)->withArgs(function (PdfRenderRequest $request) use (&$heights): bool {
            $this->assertSame(PdfDocumentType::OrderPrint, $request->documentType);
            $this->assertNull($request->format);
            $this->assertSame('Portrait', $request->orientation);
            $this->assertSame('mm', $request->paperSize['unit']);
            $this->assertSame(0, $request->margins['left']);
            $this->assertLessThanOrEqual(ReceiptPaperHeight::MAX_HEIGHT_MM, $request->paperSize['height']);
            $heights[$request->paperSize['width']][] = $request->paperSize['height'];

            return true;
        })->andReturn('%PDF-test');
        $renderer = new PrintRenderService($generator);
        foreach (PrinterPaperSize::cases() as $size) {
            foreach ([1, 12] as $count) {
                $this->assertSame('%PDF-test', $renderer->renderToPdf(PrintContentType::Bill, OrderPrintFixture::make($count), $size));
            }
        }
        $this->assertSame([58, 80], array_keys($heights));
        foreach ($heights as $sizes) {
            $this->assertGreaterThan($sizes[0], $sizes[1]);
        }
    }

    public function test_arabic_shaping_preserves_decimals_in_item_captions_and_tax_rates(): void
    {
        $this->printerLocale('ar');
        $payload = OrderPrintFixture::make(1, true);
        $payload['tax_summary'][0]['rate'] = 5.25;
        $html = app(PrintRenderService::class)->renderToHtml(PrintContentType::Invoice, $payload, PrinterPaperSize::Paper58mm);
        $html = app(\Modules\Pdf\Services\Rendering\ArabicHtmlProcessor::class)->process($html);
        preg_match_all('/<span class="number">([^<]+)<\/span>/', $html, $matches);
        foreach (['0.300', '1.5', '4.000', '5.25%'] as $value) {
            $this->assertContains($value, $matches[1]);
        }
    }

    public function test_dompdf_renders_complete_single_roll_receipts_in_both_widths_and_languages(): void
    {
        $renderer = app(PrintRenderService::class);
        foreach (['en', 'ar'] as $locale) {
            $this->printerLocale($locale);
            foreach (PrinterPaperSize::cases() as $size) {
                foreach (PrintContentType::cases() as $type) {
                    $pdf = $renderer->renderToPdf($type, OrderPrintFixture::make(4, $locale === 'ar'), $size);
                    $this->assertStringStartsWith('%PDF-', $pdf);
                    $this->assertSame(1, preg_match_all('/\/Type\s*\/Page\b/', $pdf), $locale.' '.$size->value.' '.$type->value);
                    preg_match('/\/MediaBox\s*\[\s*0[.0]*\s+0[.0]*\s+([\d.]+)\s+([\d.]+)\s*\]/', $pdf, $box);
                    $this->assertEqualsWithDelta($size->getAsNumber(), (float) $box[1] * 25.4 / 72, 0.01);
                }
            }
        }
    }

    public function test_empty_and_very_long_receipts_keep_safe_paper_sizes(): void
    {
        $renderer = app(PrintRenderService::class);
        foreach (PrintContentType::cases() as $type) {
            $html = $renderer->renderToHtml($type, [], PrinterPaperSize::Paper58mm);
            $this->assertStringContainsString('class="receipt receipt--narrow"', $html);
            $this->assertStringNotContainsString('ITEM-', $html);
        }
        $view = $renderer->build(PrintContentType::Kitchen, OrderPrintFixture::make(180), PrinterPaperSize::Paper58mm);
        $this->assertEquals(ReceiptPaperHeight::MAX_HEIGHT_MM, app(ReceiptPaperHeight::class)->measure($view, 58));
    }

    public function test_invoice_factory_uses_a_vector_qr_without_requiring_imagick(): void
    {
        Storage::fake('local');
        $invoice = new Invoice([
            'invoice_number' => 'INV-1042', 'uuid' => 'test-invoice', 'type' => InvoiceType::Simplified,
            'currency' => 'JOD', 'subtotal' => 10, 'tax_total' => 0, 'discount_total' => 0,
            'total' => 10, 'paid_amount' => 10, 'refunded_amount' => 0, 'net_paid' => 10,
            'issued_at' => '2026-09-06 12:30:00',
        ]);
        $invoice->setRelation('seller', new InvoiceParty(['type' => InvoicePartyType::Seller, 'legal_name' => 'Forkiva']));
        $invoice->setRelation('buyer', new InvoiceParty(['type' => InvoicePartyType::Buyer, 'legal_name' => 'Cash customer']));
        $invoice->setRelation('branch', null);
        foreach (['discounts', 'taxes', 'allTaxes', 'allocations', 'lines'] as $relation) {
            $invoice->setRelation($relation, collect());
        }
        $order = Mockery::mock(Order::class)->makePartial();
        $order->shouldReceive('getInvoice')->once()->andReturn($invoice);
        $payload = (new PrintInvoiceContentFactory)->resource($order);
        $this->assertStringContainsString('<svg', $payload['qrcode_svg']);
        $this->assertArrayNotHasKey('qrcode', $payload);
        $html = app(PrintRenderService::class)->renderToHtml(PrintContentType::Invoice, $payload);
        $this->assertStringContainsString('data:image/svg+xml;base64,', $html);
        $this->assertStringStartsWith('%PDF-', app(PrintRenderService::class)->renderToPdf(PrintContentType::Invoice, $payload));
    }

    public function test_waiter_factory_receipt_preserves_currency_precision_and_lists_charges(): void
    {
        $order = new Order([
            'type' => OrderType::DineIn, 'currency' => 'JOD', 'subtotal' => 10.125,
            'charge_total' => 0.375, 'total' => 10.5, 'due_amount' => 10.5,
        ]);
        foreach (['customer', 'table', 'discount'] as $relation) {
            $order->setRelation($relation, null);
        }
        foreach (['products', 'taxes'] as $relation) {
            $order->setRelation($relation, collect());
        }
        $order->setRelation('charges', collect([new OrderCharge([
            'name' => ['en' => 'Service fee'], 'currency' => 'JOD',
            'quantity' => 1, 'unit_amount' => 0.375, 'amount' => 0.375,
        ])]));
        $factory = new PrintWaiterContentFactory;
        $this->assertContains('charges', $factory->relations());
        $payload = $factory->resource($order);
        foreach (PrinterPaperSize::cases() as $size) {
            $html = app(PrintRenderService::class)->renderToHtml(PrintContentType::Waiter, $payload, $size);
            foreach (['Service fee', '10.125', '0.375', '10.500'] as $value) {
                $this->assertStringContainsString($value, $html);
            }
        }
    }

    public function test_financial_receipts_distinguish_refunds_and_delivery_handles_empty_items(): void
    {
        $renderer = app(PrintRenderService::class);
        foreach (['en', 'ar'] as $locale) {
            $this->printerLocale($locale);
            foreach ([PrintContentType::Bill, PrintContentType::Delivery, PrintContentType::Invoice] as $type) {
                $payload = OrderPrintFixture::make(0, $locale === 'ar');
                $payload['payments'][0]['type'] = 'Refund';
                $payload['allocations'] = [['payment' => ['method' => 'Cash', 'type' => 'refund'], 'amount' => 0.125]];
                foreach (PrinterPaperSize::cases() as $size) {
                    $html = $renderer->renderToHtml($type, $payload, $size);
                    $this->assertStringContainsString(__('printer::thermal_print.refund', [], $locale), $html);
                    $this->assertStringContainsString(__('printer::thermal_print.no_items', [], $locale), $html);
                }
            }
        }
    }

    public function test_kitchen_renders_only_the_selected_station_products_without_prices(): void
    {
        $payload = OrderPrintFixture::make(1);
        $payload['products'] = ['kitchen' => ['name' => 'Grill'], 'products' => $payload['products']];
        $payload['kitchens'] = [2 => ['products' => [['name' => 'Other station item', 'quantity' => 1]]]];
        $html = app(PrintRenderService::class)->renderToHtml(PrintContentType::Kitchen, $payload, PrinterPaperSize::Paper58mm);
        $this->assertStringContainsString('ITEM-1', $html);
        $this->assertStringContainsString('x1.5', strip_tags($html));
        $this->assertStringContainsString('No onions, sauce on the side', $html);
        $this->assertStringNotContainsString('Other station item', $html);
        $this->assertStringNotContainsString('4.000', $html);
    }

    private function printerLocale(string $locale): void
    {
        app()->setLocale($locale);
        app()->instance('setting', new SettingRepository(collect([
            'default_locale' => ['payload' => serialize($locale), 'is_encryptable' => false, 'is_translatable' => false],
            'pdf_default_driver' => ['payload' => serialize('dompdf'), 'is_encryptable' => false, 'is_translatable' => false],
        ])));
    }
}
