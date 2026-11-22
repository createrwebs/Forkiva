<?php

namespace Modules\Pdf\Exceptions;

use Modules\Pdf\Enums\PdfDocumentType;
use Modules\Pdf\Enums\PdfDriver;
use RuntimeException;

class PdfGenerationException extends RuntimeException
{
    /**
     * Create a sanitized exception for a failed document render.
     */
    public static function forRender(PdfDriver $driver, PdfDocumentType $type): self
    {
        return new self(__('pdf::settings.messages.generation_failed', [
            'document' => __("pdf::settings.document_types.{$type->value}"),
            'driver' => __("pdf::settings.drivers.{$driver->value}"),
        ]));
    }
}
