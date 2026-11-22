<?php

namespace Modules\Invoice\Services\InvoicePDF;

use Illuminate\Http\Response;
use Illuminate\Support\Facades\Storage;
use Modules\Invoice\Models\Invoice;
use Modules\Pdf\Data\PdfRenderRequest;
use Modules\Pdf\Enums\PdfDocumentType;
use Modules\Pdf\Services\Generator\PdfGeneratorInterface;
use Modules\Support\Money;
use Modules\Tax\Support\TaxSummary;
use Throwable;

class InvoicePDFService implements InvoicePDFServiceInterface
{
    public function __construct(protected PdfGeneratorInterface $pdfGenerator) {}

    /** {@inheritDoc} */
    public function view(string $uuid): Response
    {
        $invoice = $this->getInvoice($uuid);

        $content = $this->getContent($invoice);

        return response($content, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="invoice-'.$invoice->invoice_number.'.pdf"',
        ]);
    }

    /**
     * Get invoice
     */
    private function getInvoice(string $uuid): Invoice
    {
        $with = [
            'seller',
            'buyer',
            'discounts',
            'taxes',
            'allTaxes',
            'allocations' => fn ($query) => $query->with(['payment']),
            'lines.taxes',
            'branch:id,name',
            'referenceInvoice:id,invoice_number,uuid',
        ];

        $invoice = Invoice::query()
            ->where('uuid', $uuid)
            ->firstOrFail();

        if (is_null($invoice->file_info)) {
            $invoice = Invoice::query()
                ->with($with)
                ->where('uuid', $uuid)
                ->firstOrFail();
        }

        return $invoice;
    }

    /**
     * Get invoice pdf content
     *
     * @throws Throwable
     */
    private function getContent(Invoice $invoice): string
    {
        if (is_null($invoice->file_info)) {
            $content = $this->saveInvoiceFile($invoice);
        } else {
            $content = Storage::disk($invoice->file_info['disk'])->get($invoice->file_info['path']);
        }

        return $content;
    }

    /**
     * Store invoice as pdf file
     *
     * @throws Throwable
     */
    private function saveInvoiceFile(Invoice $invoice): string
    {
        $taxSummary = TaxSummary::summarize($invoice->allTaxes, 'invoice_line_id')
            ->map(fn (array $tax) => [
                ...$tax,
                'amount' => new Money($tax['amount'], $tax['currency']),
            ]);
        $pdfContent = $this->pdfGenerator->generate(new PdfRenderRequest(
            documentType: PdfDocumentType::Invoice,
            view: 'pdf::invoices.pdf',
            data: compact('invoice', 'taxSummary'),
            margins: ['top' => 10, 'right' => 10, 'bottom' => 10, 'left' => 10, 'unit' => 'mm'],
            metadata: [
                'title' => 'Invoice '.$invoice->invoice_number,
                'creator' => 'Forkiva',
            ],
        ));

        $path = "invoices/$invoice->invoice_number.pdf";
        $disk = 'local';

        if (Storage::disk($disk)->put($path, $pdfContent)) {
            $invoice->update(['file_info' => [
                'disk' => $disk,
                'path' => $path,
            ]]);
        }

        return $pdfContent;
    }

    /** {@inheritDoc} */
    public function download(string $uuid): Response
    {
        $invoice = $this->getInvoice($uuid);

        $content = $this->getContent($invoice);

        return response($content, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="invoice-'.$invoice->invoice_number.'.pdf"',
        ]);
    }
}
