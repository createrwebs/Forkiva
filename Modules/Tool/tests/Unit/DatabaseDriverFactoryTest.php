<?php

namespace Modules\Tool\Tests\Unit;

use Modules\Tool\Services\Database\Drivers\DatabaseDriverFactory;
use Modules\Tool\Services\Database\Drivers\MySqlDatabaseDriver;
use Modules\Tool\Services\Database\Drivers\PostgresDatabaseDriver;
use Modules\Tool\Services\Database\Drivers\SqliteDatabaseDriver;
use Tests\TestCase;

class DatabaseDriverFactoryTest extends TestCase
{
    public function test_resolves_all_supported_database_families(): void
    {
        $factory = app(DatabaseDriverFactory::class);

        $this->assertInstanceOf(MySqlDatabaseDriver::class, $factory->make('mysql'));
        $this->assertInstanceOf(MySqlDatabaseDriver::class, $factory->make('mariadb'));
        $this->assertInstanceOf(PostgresDatabaseDriver::class, $factory->make('pgsql'));
        $this->assertInstanceOf(SqliteDatabaseDriver::class, $factory->make('sqlite'));
    }
}
