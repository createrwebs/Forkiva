<?php

namespace Modules\Installer\Services\Environment;

use Illuminate\Contracts\Filesystem\FileNotFoundException;
use RuntimeException;

interface InstallerEnvironmentInterface
{
    /**
     * Atomically persist literal environment values after verifying dotenv round trips.
     *
     * @param  array<string, string|int|bool>  $values
     *
     * @throws FileNotFoundException
     * @throws RuntimeException
     */
    public function write(array $values): void;
}
