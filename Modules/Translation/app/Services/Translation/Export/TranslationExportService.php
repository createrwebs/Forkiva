<?php

namespace Modules\Translation\Services\Translation\Export;

use InvalidArgumentException;
use Modules\Translation\Enums\TranslationExportMode;
use Modules\Translation\Services\Translation\Support\TranslationBulkSupportServiceInterface;
use ZipArchive;

class TranslationExportService implements TranslationExportServiceInterface
{
    public function __construct(
        protected TranslationBulkSupportServiceInterface $supportService,
    ) {}

    /** @implements */
    public function exportLocale(string $locale, TranslationExportMode $mode = TranslationExportMode::Values): array
    {
        $translations = $this->supportService->allMergedTranslations();
        $payload = [];

        foreach ($translations as $key => $values) {
            $value = $values[$locale] ?? null;
            $isMissing = $this->supportService->isMissing($value);

            if ($mode === TranslationExportMode::EmptyMissing && ! $isMissing) {
                continue;
            }

            $payload[$key] = $mode === TranslationExportMode::Values
                ? $this->supportService->stringValue($value)
                : '';
        }

        ksort($payload);

        return $payload;
    }

    /** @implements */
    public function exportAllLocales(TranslationExportMode $mode = TranslationExportMode::Values): string
    {
        if (! class_exists(ZipArchive::class)) {
            throw new InvalidArgumentException(__('translation::translations.errors.zip_extension_unavailable'));
        }

        $path = tempnam(sys_get_temp_dir(), 'translations-');
        $zipPath = "$path.zip";
        @rename($path, $zipPath);

        $zip = new ZipArchive;
        if ($zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            throw new InvalidArgumentException(__('translation::translations.errors.zip_create_failed'));
        }

        foreach (supportedLocaleKeys() as $locale) {
            $zip->addFromString(
                "translations-$locale.json",
                $this->supportService->encodeJson($this->exportLocale($locale, $mode)),
            );
        }

        $zip->close();

        return $zipPath;
    }
}
