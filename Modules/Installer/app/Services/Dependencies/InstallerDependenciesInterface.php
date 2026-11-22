<?php

namespace Modules\Installer\Services\Dependencies;

use Illuminate\Contracts\Filesystem\FileNotFoundException;
use JsonException;

interface InstallerDependenciesInterface
{
    /**
     * Inspect the web runtime against locked production and module dependencies.
     *
     * @return array<string, array{passed: bool, detail: string, remedy: string}>
     *
     * @throws JsonException
     * @throws FileNotFoundException
     */
    public function run(): array;

    /**
     * Evaluate PHP, architecture, and extension requirements for the supplied packages.
     *
     * @param  list<array{name?: string, require?: array<string, string>}>  $packages
     * @param  array<string, string>  $extensions  Loaded extension names and versions.
     * @return array<string, array{passed: bool, detail: string, remedy: string}>
     */
    public function platform(array $packages, string $phpVersion, int $integerSize, array $extensions): array;
}
