<?php

namespace Modules\Installer\Services\Checks;

use JsonException;

interface InstallerChecksInterface
{
    /**
     * Check locked PHP dependencies, write access, configuration, and browser assets.
     *
     * @return array{checks: list<array{id: string, label: string, passed: bool, detail: string, remedy: string|null}>, passed: bool}
     *
     * @throws JsonException
     */
    public function run(): array;
}
