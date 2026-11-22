<?php

namespace Modules\Tool\Tests\Feature;

use Illuminate\Database\ConfigurationUrlParser;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Modules\Tool\Services\Database\Drivers\PostgresDatabaseDriver;
use Tests\TestCase;

class DatabaseDriverPostgresIntegrationTest extends TestCase
{
    public function test_native_postgres_backup_and_restore_round_trip_on_disposable_database(): void
    {
        if (config('database.default') !== 'pgsql') {
            $this->markTestSkipped('Run with a disposable PostgreSQL test connection.');
        }

        $config = (new ConfigurationUrlParser)->parseConfiguration(
            config('database.connections.pgsql'),
        );
        $database = (string) ($config['database'] ?? '');

        if (! str_ends_with($database, '_test')) {
            $this->fail('PostgreSQL database-tool integration tests require a database ending in _test.');
        }

        $driver = app(PostgresDatabaseDriver::class);
        $capabilities = $driver->capabilities($config);
        if (! $capabilities['backup_available'] || ! $capabilities['restore_available']) {
            $this->markTestSkipped('Native PostgreSQL backup and restore clients are required.');
        }

        $table = 'tool_database_round_trip';
        $directory = sys_get_temp_dir().'/forkiva_postgres_database_tool_'.uniqid();
        $backup = $directory.'/round-trip.sql';
        File::ensureDirectoryExists($directory, 0700, true);

        try {
            DB::statement("DROP TABLE IF EXISTS $table");
            DB::statement("CREATE TABLE $table (id BIGINT PRIMARY KEY, value VARCHAR(50) NOT NULL)");
            DB::table($table)->insert(['id' => 1, 'value' => 'before-backup']);

            $driver->backup($config, $backup);
            DB::table($table)->where('id', 1)->update(['value' => 'after-backup']);
            $driver->restore($config, $backup);

            $this->assertSame('before-backup', DB::table($table)->where('id', 1)->value('value'));
            $this->assertGreaterThan(0, File::size($backup));
        } finally {
            DB::statement("DROP TABLE IF EXISTS $table");
            File::deleteDirectory($directory);
        }
    }
}
