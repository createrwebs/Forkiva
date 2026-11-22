<?php

namespace Modules\Printer\Services\Render;

use Illuminate\View\View;
use Modules\Printer\Enum\PrintContentType;
use Modules\Printer\Enum\PrinterPaperSize;
use Throwable;

interface PrintRenderServiceInterface
{
    /**
     * Render template to base64
     *
     * @throws Throwable
     */
    public function renderToBase64(
        PrintContentType $type,
        array $payload,
        PrinterPaperSize $paperSize = PrinterPaperSize::Paper80mm,
        string $renderAs = 'image'
    ): string;

    /**
     * Render template to pdf
     *
     * @throws Throwable
     */
    public function renderToPdf(
        PrintContentType $type,
        array $payload,
        PrinterPaperSize $paperSize = PrinterPaperSize::Paper80mm
    ): string;

    /**
     * Render template to image
     *
     * @throws Throwable
     */
    public function renderToImage(
        PrintContentType $type,
        array $payload,
        PrinterPaperSize $paperSize = PrinterPaperSize::Paper80mm
    ): string;

    /**
     * Render template to html
     *
     * @throws Throwable
     */
    public function renderToHtml(
        PrintContentType $type,
        array $payload,
        PrinterPaperSize $paperSize = PrinterPaperSize::Paper80mm
    ): string;

    /**
     * Global Render template
     */
    public function build(
        PrintContentType $type,
        array $payload,
        PrinterPaperSize $paperSize = PrinterPaperSize::Paper80mm
    ): View;
}
