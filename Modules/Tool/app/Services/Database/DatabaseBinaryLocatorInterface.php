<?php

namespace Modules\Tool\Services\Database;

interface DatabaseBinaryLocatorInterface
{
    /**
     * Find the first configured or system database executable.
     *
     * @param  array<int, string|null>  $candidates
     */
    public function find(array $candidates): ?string;
}
