<?php

namespace Modules\Pdf\Services\Driver;

use Modules\Pdf\Data\PdfVerificationResult;
use Modules\Pdf\Enums\PdfDriver;

interface PdfDriverVerifierInterface
{
    /**
     * Verify driver dependencies and render valid sample documents.
     *
     * @param  array<string, mixed>  $configuration
     */
    public function verify(PdfDriver $driver, array $configuration, bool $receipt = false): PdfVerificationResult;

    /**
     * Inspect whether the driver's runtime dependencies are available.
     *
     * @param  array<string, mixed>  $configuration
     * @return array{available: bool, message: ?string}
     */
    public function dependencyStatus(PdfDriver $driver, array $configuration): array;
}
