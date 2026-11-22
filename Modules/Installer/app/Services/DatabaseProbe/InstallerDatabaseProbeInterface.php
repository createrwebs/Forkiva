<?php

namespace Modules\Installer\Services\DatabaseProbe;

use Illuminate\Database\Connection;
use Illuminate\Database\QueryException;
use RuntimeException;

interface InstallerDatabaseProbeInterface
{
    /**
     * Verify required SQL features and privileges with a disposable table.
     *
     * @throws QueryException
     * @throws RuntimeException
     */
    public function run(Connection $db): void;
}
