<?php

namespace Modules\Setting\Services\Setting;

use JsonException;
use Modules\Setting\Enums\SettingSection;
use Modules\Setting\Models\Setting;
use Throwable;

interface SettingServiceInterface
{
    /**
     * Label for the resource.
     */
    public function label(): string;

    /**
     * Model for the resource.
     */
    public function model(): string;

    /**
     * Get a new instance of the model.
     */
    public function getModel(): Setting;

    /**
     * Get application settings
     */
    public function getAppSettings(bool $refresh = false): array;

    /**
     * Get application settings version.
     */
    public function getAppSettingsVersion(): string;

    /**
     * Get settings
     */
    public function getSettings(SettingSection $section): array;

    /**
     * Get meta
     */
    public function getMeta(SettingSection $section): array;

    /**
     * Update Settings
     *
     * @throws JsonException
     * @throws Throwable
     */
    public function update(SettingSection $section, array $data): void;

    /**
     * Refresh setting binding
     */
    public function refreshSettingBinding(): void;
}
