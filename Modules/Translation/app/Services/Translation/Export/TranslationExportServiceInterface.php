<?php

namespace Modules\Translation\Services\Translation\Export;

use Modules\Translation\Enums\TranslationExportMode;

interface TranslationExportServiceInterface
{
    /**
     * Build a flat JSON export for one locale.
     *
     * @return array<string, string>
     */
    public function exportLocale(string $locale, TranslationExportMode $mode = TranslationExportMode::Values): array;

    /**
     * Build a ZIP archive containing one JSON export per supported locale.
     */
    public function exportAllLocales(TranslationExportMode $mode = TranslationExportMode::Values): string;
}
