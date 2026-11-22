<?php

namespace Modules\Tool\Tests\Feature;

use Illuminate\Database\ConfigurationUrlParser;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Modules\Tool\Services\Database\Drivers\MySqlDatabaseDriver;
use Tests\TestCase;

class DatabaseDriverMySqlIntegrationTest extends TestCase
{
    public function test_native_mysql_backup_and_restore_round_trip_on_disposable_database(): void
    {
        if (config('database.default') !== 'mysql') {
            $this->markTestSkipped('Run with phpunit.mysql.xml to use the disposable MySQL database.');
        }

        $config = (new ConfigurationUrlParser)->parseConfiguration(
            config('database.connections.mysql'),
        );
        $database = (string) ($config['database'] ?? '');

        if (! str_ends_with($database, '_test')) {
            $this->fail('MySQL database-tool integration tests require a database ending in _test.');
        }

        $driver = app(MySqlDatabaseDriver::class);
        $capabilities = $driver->capabilities($config);
        if (! $capabilities['backup_available'] || ! $capabilities['restore_available']) {
            $this->markTestSkipped('Native MySQL/MariaDB backup and restore clients are required.');
        }

        $table = 'tool_database_round_trip';
        $directory = sys_get_temp_dir().'/forkiva_mysql_database_tool_'.uniqid();
        $backup = $directory.'/round-trip.sql';
        File::ensureDirectoryExists($directory, 0700, true);

        try {
            DB::statement("DROP TABLE IF EXISTS `$table`");
            DB::statement("CREATE TABLE `$table` (`id` BIGINT UNSIGNED PRIMARY KEY, `value` VARCHAR(50) NOT NULL)");
            DB::table($table)->insert(['id' => 1, 'value' => 'before-backup']);

            $driver->backup($config, $backup);
            DB::table($table)->where('id', 1)->update(['value' => 'after-backup']);
            $driver->restore($config, $backup);

            $this->assertSame('before-backup', DB::table($table)->where('id', 1)->value('value'));
            $this->assertGreaterThan(0, File::size($backup));
        } finally {
            DB::statement("DROP TABLE IF EXISTS `$table`");
            File::deleteDirectory($directory);
        }
    }
}
