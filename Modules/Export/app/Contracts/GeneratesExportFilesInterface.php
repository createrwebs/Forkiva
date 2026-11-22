<?php

namespace Modules\Export\Contracts;

use Modules\Export\Models\Export;
use Modules\Export\Support\GeneratedExportFile;

interface GeneratesExportFilesInterface extends ExportSourceInterface
{
    /**
     * Validate source-specific context and authorization before queueing or generating.
     */
    public function validateExport(array $filters = [], ?array $sorts = [], array $context = []): void;

    /**
     * Generate and store an export without materializing the complete result set.
     */
    public function generate(Export $export): GeneratedExportFile;
}
