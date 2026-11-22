<?php

namespace Modules\Tool\Services\Database;

use Closure;

interface DatabaseRestoreGuardInterface
{
    /**
     * Quiesce HTTP and queued writes for the destructive restore window.
     */
    public function run(Closure $restore): mixed;
}
