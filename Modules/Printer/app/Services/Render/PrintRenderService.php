<?php

namespace Modules\Printer\Services\Render;

use Illuminate\View\View;
use InvalidArgumentException;
use Modules\Pdf\Data\PdfRenderRequest;
use Modules\Pdf\Enums\PdfDocumentType;
use Modules\Pdf\Services\Generator\PdfGeneratorInterface;
use Modules\Printer\Enum\PrintContentType;
use Modules\Printer\Enum\PrinterPaperSize;
use Modules\Printer\Services\Render\Templates\TemplateBuilder;
use Spatie\Browsershot\Browsershot;
use Spatie\LaravelPdf\Enums\Orientation;

class PrintRenderService implements PrintRenderServiceInterface
{
    public function __construct(protected PdfGeneratorInterface $pdfGenerator) {}

    /** {@inheritDoc} */
    public function renderToBase64(
        PrintContentType $type,
        array $payload,
        PrinterPaperSize $paperSize = PrinterPaperSize::Paper80mm,
        string $renderAs = 'image'
    ): string {
        return match ($renderAs) {
            'image' => base64_encode($this->renderToImage($type, $payload, $paperSize)),
            'pdf' => base64_encode($this->renderToPdf($type, $payload, $paperSize)),
            default => throw new InvalidArgumentException("Unsupported render output format [{$renderAs}]."),
        };
    }

    /** {@inheritDoc} */
    public function renderToImage(
        PrintContentType $type,
        array $payload,
        PrinterPaperSize $paperSize = PrinterPaperSize::Paper80mm
    ): string {
        return $this->createBrowsershot(
            $this->build($type, $payload, $paperSize)->with('rasterOutput', true)->render(),
            $paperSize
        )
            ->setScreenshotType('png')
            ->select('.receipt')
            ->screenshot();
    }

    /**
     * Create a shared Browsershot instance configured for receipt rendering.
     */
    private function createBrowsershot(string $html, PrinterPaperSize $paperSize): Browsershot
    {
        $profile = $this->resolveMediaProfile($paperSize);

        return Browsershot::html($html)
            ->noSandbox()
            ->emulateMedia('print')
            ->windowSize($profile['pixel_width'], 6000)
            // Use the receipt's whole CSS-pixel width to avoid fractional clip rounding.
            ->setOption('viewport.deviceScaleFactor', $profile['pixel_width'] / floor($profile['printable_width_mm'] * 96 / 25.4))
            ->showBackground()
            ->setOption('defaultBackgroundColor', [
                'r' => 255,
                'g' => 255,
                'b' => 255,
                'a' => 1,
            ])
            ->addChromiumArguments([
                'font-render-hinting' => 'none',
                'force-color-profile' => 'srgb',
            ]);
    }

    /**
     * Resolve configured media profile.
     *
     * @return array{paper_width_mm:int,printable_width_mm:float,pixel_width:int}
     */
    private function resolveMediaProfile(PrinterPaperSize $paperSize): array
    {
        $profile = config('printer.media_profiles.'.$paperSize->value, []);
        $paperWidthMm = (int) ($profile['paper_width_mm'] ?? 80);

        return [
            ...$profile,
            'paper_width_mm' => $paperWidthMm,
            'printable_width_mm' => (float) ($profile['printable_width_mm'] ?? ($paperWidthMm <= 58 ? 48 : 72)),
            'pixel_width' => (int) ($profile['pixel_width'] ?? 576),
        ];
    }

    /** {@inheritDoc} */
    public function renderToHtml(
        PrintContentType $type,
        array $payload,
        PrinterPaperSize $paperSize = PrinterPaperSize::Paper80mm
    ): string {
        return $this->build($type, $payload, $paperSize)->render();
    }

    /** {@inheritDoc} */
    public function build(
        PrintContentType $type,
        array $payload,
        PrinterPaperSize $paperSize = PrinterPaperSize::Paper80mm
    ): View {
        return (new TemplateBuilder)->build($type, $payload, $paperSize);
    }

    /** {@inheritDoc} */
    public function renderToPdf(
        PrintContentType $type,
        array $payload,
        PrinterPaperSize $paperSize = PrinterPaperSize::Paper80mm
    ): string {
        $profile = $this->resolveMediaProfile($paperSize);
        $view = $this->build($type, $payload, $paperSize);

        return $this->pdfGenerator->generate(new PdfRenderRequest(
            documentType: PdfDocumentType::OrderPrint,
            view: $view->name(),
            data: $view->getData(),
            format: null,
            orientation: Orientation::Portrait->value,
            paperSize: [
                'width' => $profile['paper_width_mm'],
                'height' => app(ReceiptPaperHeight::class)->measure($view, $profile['paper_width_mm']),
                'unit' => 'mm',
            ],
        ));
    }
}
