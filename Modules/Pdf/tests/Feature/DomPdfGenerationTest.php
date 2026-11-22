<?php

namespace Modules\Pdf\Tests\Feature;

use Modules\Pdf\Data\PdfRenderRequest;
use Modules\Pdf\Enums\PdfDocumentType;
use Modules\Pdf\Services\Generator\PdfGeneratorInterface;
use Modules\Setting\Repositories\SettingRepository;
use Tests\TestCase;

class DomPdfGenerationTest extends TestCase
{
    public function test_default_driver_generates_a_valid_pdf_for_every_document_type(): void
    {
        app()->instance('setting', new SettingRepository(collect([
            'pdf_default_driver' => [
                'payload' => serialize('dompdf'),
                'is_encryptable' => false,
                'is_translatable' => false,
            ],
        ])));

        foreach (PdfDocumentType::cases() as $documentType) {
            $content = app(PdfGeneratorInterface::class)->generate(new PdfRenderRequest(
                documentType: $documentType,
                view: 'pdf::health-check',
                data: ['driver' => 'dompdf', 'checkedAt' => now()],
            ));

            $this->assertStringStartsWith('%PDF-', $content, $documentType->value);
            $this->assertStringContainsString('%%EOF', substr($content, -1024), $documentType->value);
            $this->assertGreaterThan(1000, strlen($content), $documentType->value);
        }
    }

    public function test_default_driver_generates_a_valid_receipt_sized_pdf(): void
    {
        app()->instance('setting', new SettingRepository(collect()));

        $content = app(PdfGeneratorInterface::class)->generate(new PdfRenderRequest(
            documentType: PdfDocumentType::OrderPrint,
            view: 'pdf::health-check',
            data: ['driver' => 'dompdf', 'checkedAt' => now()],
            format: null,
            paperSize: ['width' => 80, 'height' => 200, 'unit' => 'mm'],
        ));

        $this->assertStringStartsWith('%PDF-', $content);
        $this->assertStringContainsString('%%EOF', substr($content, -1024));
        $this->assertMatchesRegularExpression('/\/MediaBox\s*\[\s*0(?:\.0+)?\s+0(?:\.0+)?\s+22[56-8](?:\.\d+)?\s+56[56-8](?:\.\d+)?\s*\]/', $content);
    }
}
