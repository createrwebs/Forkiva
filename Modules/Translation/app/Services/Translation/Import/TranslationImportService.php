<?php

namespace Modules\Translation\Services\Translation\Import;

use Illuminate\Http\UploadedFile;
use Modules\Translation\Enums\TranslationImportMode;
use Modules\Translation\Models\Translation;
use Modules\Translation\Services\Translation\Support\TranslationBulkSupportServiceInterface;

class TranslationImportService implements TranslationImportServiceInterface
{
    public function __construct(
        protected TranslationBulkSupportServiceInterface $supportService,
    ) {}

    /** @implements */
    public function previewImport(?string $locale, UploadedFile $file): array
    {
        if ($this->supportService->isZipFile($file)) {
            return $this->previewZipImport($file);
        }

        $locale = $this->supportService->requireLocale($locale);

        return $this->buildImportPreview($locale, $this->supportService->readJsonFile($file));
    }

    /** @implements */
    public function import(?string $locale, UploadedFile $file, TranslationImportMode $mode = TranslationImportMode::FillMissing): array
    {
        if ($this->supportService->isZipFile($file)) {
            return $this->importZip($file, $mode);
        }

        $locale = $this->supportService->requireLocale($locale);
        $payload = $this->supportService->readJsonFile($file);
        $preview = $this->buildImportPreview($locale, $payload);
        $updated = $this->importPayload($locale, $payload, $mode);

        return [
            ...$preview,
            'updated' => $updated,
            'mode' => $mode->value,
        ];
    }

    /**
     * Preview all locale payloads inside a ZIP import.
     *
     * @return array<string, mixed>
     */
    private function previewZipImport(UploadedFile $file): array
    {
        $files = $this->supportService->readZipJsonFiles($file);
        $locales = [];
        $total = $valid = $unknown = $emptySkipped = $wouldFillMissing = $wouldOverwrite = 0;
        $placeholderWarningCount = 0;

        foreach ($files as $locale => $payload) {
            $preview = $this->buildImportPreview($locale, $payload);
            $locales[$locale] = $preview;
            $total += $preview['total'];
            $valid += $preview['valid'];
            $unknown += $preview['unknown'];
            $emptySkipped += $preview['empty_skipped'];
            $wouldFillMissing += $preview['would_fill_missing'];
            $wouldOverwrite += $preview['would_overwrite'];
            $placeholderWarningCount += $preview['placeholder_warning_count'];
        }

        return [
            'locale' => null,
            'is_zip' => true,
            'locales' => $locales,
            'total' => $total,
            'valid' => $valid,
            'unknown' => $unknown,
            'unknown_keys' => [],
            'empty_skipped' => $emptySkipped,
            'would_fill_missing' => $wouldFillMissing,
            'would_overwrite' => $wouldOverwrite,
            'placeholder_warnings' => [],
            'placeholder_warning_count' => $placeholderWarningCount,
        ];
    }

    /**
     * Build the import preview summary for a locale payload.
     *
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private function buildImportPreview(string $locale, array $payload): array
    {
        $knownTranslations = $this->supportService->allMergedTranslations();
        $unknownKeys = [];
        $emptySkipped = 0;
        $valid = 0;
        $wouldFillMissing = 0;
        $wouldOverwrite = 0;
        $placeholderWarnings = [];

        foreach ($payload as $key => $value) {
            if ($this->supportService->isMissing($value)) {
                $emptySkipped++;

                continue;
            }

            if (! array_key_exists($key, $knownTranslations)) {
                $unknownKeys[] = $key;

                continue;
            }

            $valid++;
            $currentValue = $knownTranslations[$key][$locale] ?? null;
            if ($this->supportService->isMissing($currentValue)) {
                $wouldFillMissing++;
            } else {
                $wouldOverwrite++;
            }

            $currentString = $this->supportService->stringValue($currentValue);
            $incomingString = $this->supportService->stringValue($value);
            $missingPlaceholders = array_values(array_diff(
                $this->supportService->extractPlaceholders($currentString),
                $this->supportService->extractPlaceholders($incomingString),
            ));

            if (count($missingPlaceholders) > 0) {
                $placeholderWarnings[] = [
                    'key' => $key,
                    'missing_placeholders' => $missingPlaceholders,
                ];
            }
        }

        return [
            'locale' => $locale,
            'total' => count($payload),
            'valid' => $valid,
            'unknown' => count($unknownKeys),
            'unknown_keys' => array_slice($unknownKeys, 0, 50),
            'empty_skipped' => $emptySkipped,
            'would_fill_missing' => $wouldFillMissing,
            'would_overwrite' => $wouldOverwrite,
            'placeholder_warnings' => array_slice($placeholderWarnings, 0, 50),
            'placeholder_warning_count' => count($placeholderWarnings),
        ];
    }

    /**
     * Import all locale payloads from a ZIP archive.
     *
     * @return array<string, mixed>
     */
    private function importZip(UploadedFile $file, TranslationImportMode $mode): array
    {
        $files = $this->supportService->readZipJsonFiles($file);
        $preview = $this->previewZipImport($file);
        $updated = 0;

        foreach ($files as $locale => $payload) {
            $updated += $this->importPayload($locale, $payload, $mode, false);
        }

        $this->supportService->flushTranslationCache();

        return [
            ...$preview,
            'updated' => $updated,
            'mode' => $mode->value,
        ];
    }

    /**
     * Apply a locale payload to database-backed translations.
     *
     * @param  array<string, mixed>  $payload
     */
    private function importPayload(
        string $locale,
        array $payload,
        TranslationImportMode $mode,
        bool $flushCache = true,
    ): int {
        $knownTranslations = $this->supportService->allMergedTranslations();
        $updated = 0;

        foreach ($payload as $key => $value) {
            if (! is_string($key) || ! array_key_exists($key, $knownTranslations) || $this->supportService->isMissing($value)) {
                continue;
            }

            $currentValue = $knownTranslations[$key][$locale] ?? null;
            if ($mode === TranslationImportMode::FillMissing && ! $this->supportService->isMissing($currentValue)) {
                continue;
            }

            $translation = Translation::query()->firstOrNew(['key' => $key]);
            $translation->setTranslation('value', $locale, $this->supportService->stringValue($value))->save();
            $updated++;
        }

        if ($flushCache) {
            $this->supportService->flushTranslationCache();
        }

        return $updated;
    }
}
