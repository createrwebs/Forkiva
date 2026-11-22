<?php

namespace Modules\Installer\Services\Configuration;

use Illuminate\Contracts\Filesystem\FileNotFoundException;
use Illuminate\Validation\ValidationException;
use Random\RandomException;

interface InstallerConfigurationInterface
{
    /**
     * Return supported countries, locales, currencies, and time zones for the setup form.
     *
     * @return array{countries: array<mixed>, locales: array<mixed>, currencies: array<mixed>, timezones: array<mixed>}
     */
    public function options(): array;

    /**
     * Save validated installation defaults and rotate generated application keys once.
     *
     * @param  array<string, mixed>  $data
     *
     * @throws FileNotFoundException
     * @throws RandomException
     */
    public function persist(array $data): void;

    /**
     * Apply saved business defaults after the settings tables are available.
     *
     * @param  array<string, mixed>  $data
     */
    public function apply(array $data): void;

    /**
     * Verify that a cache store supports persistence, tags, and exclusive locks.
     *
     * @throws ValidationException
     */
    public function verifyCache(string $store): void;
}
