<?php

namespace Modules\Pdf\Services\Generator;

use Modules\Pdf\Data\PdfRenderRequest;

interface PdfGeneratorInterface
{
    /**
     * Generate PDF bytes for the requested document.
     */
    public function generate(PdfRenderRequest $request): string;
}
