<?php

namespace Modules\Export\Support;

readonly class GeneratedExportFile
{
    /**
     * Create a generated export file descriptor.
     */
    public function __construct(
        public string $disk,
        public string $path,
        public string $fileName,
    ) {}
}
