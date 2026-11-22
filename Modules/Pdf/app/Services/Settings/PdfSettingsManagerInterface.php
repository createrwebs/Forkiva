<?php

namespace Modules\Pdf\Services\Settings;

use JsonException;
use Modules\Pdf\Enums\PdfDriver;
use Throwable;

interface PdfSettingsManagerInterface
{
    /**
     * Get public PDF settings without exposing stored secrets.
     *
     * @return array<string, mixed>
     */
    public function settings(): array;

    /**
     * Get driver requirements, availability, and verification metadata.
     *
     * @return array<string, mixed>
     */
    public function meta(): array;

    /**
     * Verify and atomically persist validated settings for the active driver.
     *
     * @param  array<string, mixed>  $data
     *
     * @throws JsonException
     * @throws Throwable
     */
    public function update(array $data): void;

    /**
     * Resolve the globally active PDF driver.
     */
    public function resolveDriver(): PdfDriver;

    /**
     * Build the effective configuration for a driver.
     *
     * @param  array<string, mixed>|null  $source
     * @return array<string, mixed>
     */
    public function driverConfiguration(PdfDriver $driver, ?array $source = null): array;
}
