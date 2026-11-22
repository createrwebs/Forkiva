<?php

namespace Modules\Invoice\Tests\Feature;

use Illuminate\Support\Facades\Storage;
use Modules\Branch\Models\Branch;
use Modules\Invoice\Enums\InvoiceKind;
use Modules\Invoice\Models\Invoice;
use Modules\Invoice\Models\InvoiceLine;
use Modules\Invoice\Models\InvoiceParty;
use Modules\Pdf\Data\PdfRenderRequest;
use Modules\Pdf\Enums\PdfDocumentType;
use Modules\Pdf\Services\Generator\PdfGeneratorInterface;
use Modules\Setting\Repositories\SettingRepository;
use Modules\Support\Money;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class InvoicePdfRenderTest extends TestCase
{
    /** {@inheritDoc} */
    protected function setUp(): void
    {
        parent::setUp();

        app()->instance('setting', new SettingRepository(collect([
            'pdf_default_driver' => 'dompdf',
            'default_locale' => 'en',
            'app_name' => ['en' => 'Forkiva Kitchen', 'ar' => 'مطبخ فوركيفا'],
        ])->map(fn (mixed $value, string $key): array => [
            'payload' => serialize($value),
            'is_encryptable' => false,
            'is_translatable' => $key === 'app_name',
        ])));

        Storage::fake('local');
        Storage::disk('local')->put('default-logo.base64', base64_encode(
            '<svg xmlns="http://www.w3.org/2000/svg" width="32" height="32"><rect width="32" height="32" fill="#f57c00"/></svg>'
        ));
    }

    /**
     * Ordinary invoices and credit notes must not gain a second page from Cairo line spacing.
     */
    #[DataProvider('documentCases')]
    public function test_short_document_fits_one_a4_page_with_embedded_cairo(string $locale, InvoiceKind $kind): void
    {
        app()->setLocale($locale);

        $content = $this->renderInvoice($this->makeInvoice($kind));

        $this->assertStringStartsWith('%PDF-', $content);
        $this->assertStringContainsString('%%EOF', substr($content, -1024));
        $this->assertSame(1, preg_match_all('/\/Type\s*\/Page\b/', $content));
        $this->assertMatchesRegularExpression('/\/BaseFont\s*\/[^\s]*Cairo/', $content);
        $this->assertMatchesRegularExpression('/\/MediaBox\s*\[0\.000 0\.000 595\.28\d 841\.89\d\]/', $content);
    }

    /**
     * A real long table must paginate instead of clipping or failing the render.
     */
    public function test_long_arabic_invoice_renders_multiple_pages(): void
    {
        app()->setLocale('ar');

        $content = $this->renderInvoice($this->makeInvoice(InvoiceKind::Standard, 40));

        $this->assertStringStartsWith('%PDF-', $content);
        $this->assertStringContainsString('%%EOF', substr($content, -1024));
        $this->assertGreaterThan(1, preg_match_all('/\/Type\s*\/Page\b/', $content));
    }

    public function test_long_arabic_item_descriptions_keep_their_reading_order(): void
    {
        app()->setLocale('ar');
        $invoice = $this->makeInvoice(InvoiceKind::Standard);
        $description = 'وجبة الدجاج المشوي مع الأرز والخضار الطازجة والصلصة الخاصة والخبز العربي';
        $invoice->lines->first()->description = ['en' => 'Grilled chicken meal', 'ar' => $description];
        $html = view('pdf::invoices.pdf', ['invoice' => $invoice, 'taxSummary' => collect(), 'pdfDriver' => \Modules\Pdf\Enums\PdfDriver::Dompdf])->render();
        $processed = app(\Modules\Pdf\Services\Rendering\ArabicHtmlProcessor::class)->process($html, 210, [
            'top' => 10, 'right' => 10, 'bottom' => 10, 'left' => 10, 'unit' => 'mm',
        ]);
        $document = new \DOMDocument;
        $document->loadHTML('<?xml encoding="UTF-8">'.$processed, LIBXML_NONET | LIBXML_NOERROR | LIBXML_NOWARNING);
        $lines = (new \DOMXPath($document))->query('//td[@class="line-description"]//*[@data-dompdf-line]');
        $logicalLines = [];
        foreach ($lines as $line) {
            $logicalLines[] = $line->getAttribute('data-dompdf-line');
        }
        $this->assertGreaterThan(1, count($logicalLines));
        $this->assertSame($description, implode(' ', $logicalLines));
        $this->assertStringContainsString('ITEM-1', $document->textContent);
    }

    /**
     * Branding follows the application locale without replacing the legal seller details.
     */
    public function test_header_uses_the_localized_app_name_without_branch_or_email(): void
    {
        $invoice = $this->makeInvoice(InvoiceKind::Standard);

        foreach (['en' => 'Forkiva Kitchen', 'ar' => 'مطبخ فوركيفا'] as $locale => $name) {
            app()->setLocale($locale);
            $document = new \DOMDocument;
            $document->loadHTML(view('pdf::invoices.pdf', ['invoice' => $invoice, 'taxSummary' => collect()])->render(), LIBXML_NONET | LIBXML_NOERROR | LIBXML_NOWARNING);
            $xpath = new \DOMXPath($document);
            $header = $xpath->query('//table[@class="document-header"]')->item(0)->textContent;

            $this->assertSame($name, trim($xpath->query('//div[@class="brand-name"]')->item(0)->textContent));
            $this->assertStringNotContainsString($invoice->branch->name, $header);
            $this->assertStringNotContainsString($invoice->seller->email, $header);
            $this->assertSame($invoice->seller->legal_name, trim($xpath->query('//div[@class="party-name"]')->item(0)->textContent));
        }
    }

    /**
     * Unconfigured branding keeps the invoice identifiable by its legal seller name.
     */
    public function test_header_falls_back_to_the_seller_when_app_name_is_missing(): void
    {
        app('setting')->offsetUnset('app_name');
        $invoice = $this->makeInvoice(InvoiceKind::Standard);
        $document = new \DOMDocument;
        $document->loadHTML(view('pdf::invoices.pdf', ['invoice' => $invoice, 'taxSummary' => collect()])->render(), LIBXML_NONET | LIBXML_NOERROR | LIBXML_NOWARNING);

        $this->assertSame($invoice->seller->legal_name, trim((new \DOMXPath($document))->query('//div[@class="brand-name"]')->item(0)->textContent));
    }

    /**
     * Supply both document kinds in the supported invoice locales.
     *
     * @return array<string, array{string, InvoiceKind}>
     */
    public static function documentCases(): array
    {
        return [
            'English invoice' => ['en', InvoiceKind::Standard],
            'Arabic invoice' => ['ar', InvoiceKind::Standard],
            'English credit note' => ['en', InvoiceKind::CreditNote],
            'Arabic credit note' => ['ar', InvoiceKind::CreditNote],
        ];
    }

    /**
     * Render through the production generator, including its resolved driver context.
     */
    private function renderInvoice(Invoice $invoice): string
    {
        return app(PdfGeneratorInterface::class)->generate(new PdfRenderRequest(
            documentType: PdfDocumentType::Invoice,
            view: 'pdf::invoices.pdf',
            data: [
                'invoice' => $invoice,
                'taxSummary' => collect([
                    ['name' => 'Service Charge 10%', 'scope' => 'global', 'type' => 'exclusive', 'rate' => 10, 'amount' => new Money(3.10, 'USD')],
                    ['name' => 'VAT 15%', 'scope' => 'global', 'type' => 'exclusive', 'rate' => 15, 'amount' => new Money(4.04, 'USD')],
                ]),
            ],
            margins: ['top' => 14, 'right' => 14, 'bottom' => 14, 'left' => 14, 'unit' => 'mm'],
        ));
    }

    /**
     * Build an in-memory invoice without querying or modifying business records.
     */
    private function makeInvoice(InvoiceKind $kind, int $lineCount = 1): Invoice
    {
        $invoice = new Invoice([
            'invoice_number' => 'INV-PDF-001',
            'uuid' => '4c561396-6de3-470c-9fc1-cfe5732078dc',
            'invoice_kind' => $kind,
            'issued_at' => '2026-09-05 10:00:00',
            'currency' => 'USD',
            'subtotal' => 26.93 * $lineCount,
            'total' => 26.93 * $lineCount + 7.14,
            'paid_amount' => 26.93 * $lineCount + 7.14,
            'refunded_amount' => 0,
            'net_paid' => 26.93 * $lineCount + 7.14,
        ]);

        $invoice->setRelation('seller', new InvoiceParty([
            'legal_name' => 'Forkiva فوركيفـا',
            'address_line1' => 'Amman, Amman, Jordan',
            'country_code' => 'JO',
            'vat_tin' => 'TAX-123456',
            'cr_number' => 'CR-123456',
            'email' => 'billing@example.test',
            'phone' => '+962790000000',
        ]));
        $invoice->setRelation('buyer', new InvoiceParty(['legal_name' => 'عميل نقدي / Walk-in Customer']));
        $invoice->setRelation('branch', new Branch(['name' => 'Main Branch']));
        $invoice->setRelation('referenceInvoice', $kind->isCreditNote() ? new Invoice(['invoice_number' => 'INV-ORIGINAL-001']) : null);
        $invoice->setRelation('discounts', collect());
        $invoice->setRelation('allocations', collect());
        $invoice->setRelation('lines', collect(range(1, $lineCount))->map(fn (int $number): InvoiceLine => new InvoiceLine([
            'description' => ['en' => 'Strawberry Scoop', 'ar' => 'كرة آيس كريم فراولة'],
            'sku' => 'ITEM-'.$number,
            'quantity' => 1,
            'currency' => 'USD',
            'unit_price' => 26.93,
            'tax_amount' => 0,
            'line_total_excl_tax' => 26.93,
            'line_total_incl_tax' => 26.93,
        ])));

        return $invoice;
    }
}
