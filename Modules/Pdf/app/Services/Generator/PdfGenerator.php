<?php

namespace Modules\Pdf\Services\Generator;

use Illuminate\Support\Facades\Log;
use Modules\Pdf\Data\PdfRenderRequest;
use Modules\Pdf\Enums\PdfDriver;
use Modules\Pdf\Exceptions\PdfGenerationException;
use Modules\Pdf\Services\Driver\PdfDriverConfigurator;
use Modules\Pdf\Services\Rendering\ArabicHtmlProcessor;
use Modules\Pdf\Services\Settings\PdfSettingsManagerInterface;
use Spatie\LaravelPdf\Facades\Pdf;
use Spatie\LaravelPdf\PdfBuilder;
use Throwable;

class PdfGenerator implements PdfGeneratorInterface
{
    /**
     * Create the PDF generator.
     */
    public function __construct(
        protected PdfSettingsManagerInterface $settings,
        protected PdfDriverConfigurator $configurator,
        protected ArabicHtmlProcessor $arabicHtmlProcessor,
    ) {}

    /** {@inheritDoc} */
    public function generate(PdfRenderRequest $request): string
    {
        $driver = $this->settings->resolveDriver();
        $configuration = $this->settings->driverConfiguration($driver);

        try {
            return $this->configurator->run(
                $driver,
                $configuration,
                fn (): string => $this->build($request, $driver)->generatePdfContent(),
            );
        } catch (Throwable $exception) {
            Log::error('PDF generation failed.', [
                'driver' => $driver->value,
                'document_type' => $request->documentType->value,
                'exception' => $exception::class,
            ]);

            throw PdfGenerationException::forRender($driver, $request->documentType);
        }
    }

    /**
     * Build a configured Spatie PDF document.
     */
    private function build(PdfRenderRequest $request, PdfDriver $driver): PdfBuilder
    {
        $data = [...$request->data, 'pdfDriver' => $driver];

        $builder = ($driver === PdfDriver::Dompdf
            ? Pdf::html($this->arabicHtmlProcessor->process(
                view($request->view, $data)->render(),
                $request->paperSize === null && strtolower($request->format ?? 'a4') === 'a4'
                    ? (strtolower($request->orientation) === 'landscape' ? 297 : 210)
                    : null,
                $request->margins,
            ))
            : Pdf::view($request->view, $data))
            ->driver($driver->value)
            ->orientation($request->orientation)
            ->margins(
                $request->margins['top'],
                $request->margins['right'],
                $request->margins['bottom'],
                $request->margins['left'],
                $request->margins['unit'] ?? 'mm',
            );

        if ($request->paperSize !== null) {
            $builder->paperSize(
                $request->paperSize['width'],
                $request->paperSize['height'],
                $request->paperSize['unit'] ?? 'mm',
            );
        } elseif ($request->format !== null) {
            $builder->format($request->format);
        }

        if ($request->metadata !== []) {
            $builder->meta(
                title: $request->metadata['title'] ?? null,
                author: $request->metadata['author'] ?? null,
                subject: $request->metadata['subject'] ?? null,
                keywords: $request->metadata['keywords'] ?? null,
                creator: $request->metadata['creator'] ?? 'Forkiva',
            );
        }

        return $builder;
    }
}
