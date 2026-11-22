<?php

namespace Modules\Tool\Services\Database\Drivers;

use RuntimeException;

readonly class DatabaseDriverFactory
{
    public function __construct(
        private MySqlDatabaseDriver $mysql,
        private PostgresDatabaseDriver $postgres,
        private SqliteDatabaseDriver $sqlite,
    ) {}

    /**
     * Resolve the driver implementation for an active Laravel connection.
     */
    public function make(string $driver): DatabaseDriverInterface
    {
        return match ($driver) {
            'mysql',
            'mariadb' => $this->mysql,
            'pgsql' => $this->postgres,
            'sqlite' => $this->sqlite,
            default => throw new RuntimeException(__('tool::database.messages.unsupported_driver', [
                'driver' => $driver,
            ])),
        };
    }
}
