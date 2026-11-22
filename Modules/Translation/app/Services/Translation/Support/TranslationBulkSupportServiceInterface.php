<?php

namespace Modules\Translation\Services\Translation\Support;

use Illuminate\Http\UploadedFile;

interface TranslationBulkSupportServiceInterface
{
    /**
     * Get all known translation keys merged across file and database sources.
     *
     * @return array<string, array<string, mixed>>
     */
    public function allMergedTranslations(): array;

    /**
     * Determine whether a translation value should be treated as missing.
     */
    public function isMissing(mixed $value): bool;

    /**
     * Encode a translation payload to pretty JSON.
     *
     * @param  array<string, string>  $payload
     */
    public function encodeJson(array $payload): string;

    /**
     * Normalize a translation value to a string representation.
     */
    public function stringValue(mixed $value): string;

    /**
     * Determine whether the uploaded file is a ZIP archive.
     */
    public function isZipFile(UploadedFile $file): bool;

    /**
     * Read all valid locale JSON payloads from a ZIP archive.
     *
     * @return array<string, array<string, mixed>>
     */
    public function readZipJsonFiles(UploadedFile $file): array;

    /**
     * Validate and normalize a decoded JSON translation payload.
     *
     * @return array<string, mixed>
     */
    public function validateJsonPayload(mixed $decoded): array;

    /**
     * Ensure a locale is present for single-locale JSON import.
     */
    public function requireLocale(?string $locale): string;

    /**
     * Read and validate a JSON translation file.
     *
     * @return array<string, mixed>
     */
    public function readJsonFile(UploadedFile $file): array;

    /**
     * Extract placeholder tokens from a translation value.
     *
     * @return array<int, string>
     */
    public function extractPlaceholders(string $value): array;

    /**
     * Flush the cached translation catalogue.
     */
    public function flushTranslationCache(): void;
}
