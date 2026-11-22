<?php

namespace Modules\Translation\Services\Translation\Support;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Cache;
use InvalidArgumentException;
use Modules\Translation\Models\Translation;
use ZipArchive;

class TranslationBulkSupportService implements TranslationBulkSupportServiceInterface
{
    /** @implements */
    public function allMergedTranslations(): array
    {
        return Translation::allMergedTranslations();
    }

    /** @implements */
    public function isMissing(mixed $value): bool
    {
        return $value === null || $value === '';
    }

    /** @implements */
    public function encodeJson(array $payload): string
    {
        return json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES).PHP_EOL;
    }

    /** @implements */
    public function stringValue(mixed $value): string
    {
        if ($value === null) {
            return '';
        }

        if (is_scalar($value)) {
            return (string) $value;
        }

        return json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: '';
    }

    /** @implements */
    public function isZipFile(UploadedFile $file): bool
    {
        return strtolower($file->getClientOriginalExtension()) === 'zip'
            || in_array($file->getMimeType(), ['application/zip', 'application/x-zip-compressed'], true);
    }

    /** @implements */
    public function readZipJsonFiles(UploadedFile $file): array
    {
        if (! class_exists(ZipArchive::class)) {
            throw new InvalidArgumentException(__('translation::translations.errors.zip_extension_unavailable'));
        }

        $zip = new ZipArchive;
        if ($zip->open($file->getRealPath()) !== true) {
            throw new InvalidArgumentException(__('translation::translations.errors.zip_read_failed'));
        }

        $files = [];
        for ($i = 0; $i < $zip->numFiles; $i++) {
            $name = (string) $zip->getNameIndex($i);
            if (! str_ends_with(strtolower($name), '.json')) {
                continue;
            }

            $locale = $this->localeFromZipName($name);
            if (! $locale) {
                continue;
            }

            $content = $zip->getFromIndex($i);
            if ($content === false) {
                continue;
            }

            $files[$locale] = $this->validateJsonPayload(json_decode($content, true));
        }

        $zip->close();

        if (count($files) === 0) {
            throw new InvalidArgumentException(__('translation::translations.errors.zip_missing_json_files'));
        }

        return $files;
    }

    /** @implements */
    public function validateJsonPayload(mixed $decoded): array
    {
        if (! is_array($decoded) || array_is_list($decoded)) {
            throw new InvalidArgumentException(__('translation::translations.errors.json_flat_object_required'));
        }

        foreach ($decoded as $key => $value) {
            if (! is_string($key) || str_contains($key, "\n") || is_array($value) || is_object($value)) {
                throw new InvalidArgumentException(__('translation::translations.errors.json_flat_scalar_required'));
            }
        }

        return $decoded;
    }

    /** @implements */
    public function requireLocale(?string $locale): string
    {
        if (! $locale) {
            throw new InvalidArgumentException(__('translation::translations.errors.locale_required_for_json_import'));
        }

        return $locale;
    }

    /** @implements */
    public function readJsonFile(UploadedFile $file): array
    {
        $decoded = json_decode((string) file_get_contents($file->getRealPath()), true);

        return $this->validateJsonPayload($decoded);
    }

    /** @implements */
    public function extractPlaceholders(string $value): array
    {
        preg_match_all('/:[A-Za-z_][A-Za-z0-9_]*/', $value, $matches);

        return array_values(array_unique($matches[0] ?? []));
    }

    /** @implements */
    public function flushTranslationCache(): void
    {
        Cache::tags('translations')->flush();
    }

    /**
     * Resolve a locale key from a ZIP entry name.
     */
    private function localeFromZipName(string $name): ?string
    {
        $baseName = pathinfo($name, PATHINFO_FILENAME);
        $locale = str_starts_with($baseName, 'translations-')
            ? substr($baseName, strlen('translations-'))
            : $baseName;

        return in_array($locale, supportedLocaleKeys(), true) ? $locale : null;
    }
}
