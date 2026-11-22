<?php

namespace Modules\Translation\Services\Translation\Import;

use Illuminate\Http\UploadedFile;
use Modules\Translation\Enums\TranslationImportMode;

interface TranslationImportServiceInterface
{
    /**
     * Preview a flat JSON or ZIP translation import.
     *
     * @return array<string, mixed>
     */
    public function previewImport(?string $locale, UploadedFile $file): array;

    /**
     * Import a flat JSON or ZIP translation file.
     *
     * @return array<string, mixed>
     */
    public function import(?string $locale, UploadedFile $file, TranslationImportMode $mode = TranslationImportMode::FillMissing): array;
}
