<?php

namespace Modules\Tool\Tests\Unit;

use Modules\Tool\Services\Database\Drivers\MySqlDatabaseDriver;
use Modules\Tool\Services\Database\Drivers\PostgresDatabaseDriver;
use Tests\TestCase;

class CommandDatabaseDriverTest extends TestCase
{
    private string $temporaryDirectory;

    private string $binary;

    protected function setUp(): void
    {
        parent::setUp();

        $this->temporaryDirectory = sys_get_temp_dir().'/forkiva_command_driver_'.uniqid();
        mkdir($this->temporaryDirectory, 0700, true);
        $this->binary = $this->temporaryDirectory.'/fake-database-client';
        file_put_contents($this->binary, <<<'PHP'
            #!/usr/bin/env php
            <?php
            $destination = null;
            foreach (array_slice($argv, 1) as $argument) {
                if (str_starts_with($argument, '--result-file=') || str_starts_with($argument, '--file=')) {
                    $destination = substr($argument, strpos($argument, '=') + 1);
                }
            }
            if ($destination !== null) {
                $content = in_array('--format=plain', $argv, true)
                    ? "-- PostgreSQL database dump\nSELECT 1;\n"
                    : "-- MySQL dump\nSELECT 1;\n";
                file_put_contents($destination, $content);
                exit(0);
            }
            exit(stream_get_contents(STDIN) === '' ? 2 : 0);
            PHP);
        chmod($this->binary, 0700);

        config([
            'tool.database.dump_binary' => basename($this->binary),
            'tool.database.restore_binary' => basename($this->binary),
            'tool.database.binary_directories' => [$this->temporaryDirectory],
        ]);
    }

    protected function tearDown(): void
    {
        @unlink($this->binary);
        @rmdir($this->temporaryDirectory);

        parent::tearDown();
    }

    public function test_mysql_driver_uses_configured_native_tools_for_streamed_round_trip(): void
    {
        $driver = app(MySqlDatabaseDriver::class);
        $path = $this->temporaryDirectory.'/mysql.sql';
        $config = $this->connectionConfig('mysql');

        $this->assertTrue($driver->capabilities($config)['restore_available']);
        $this->assertSame('fake-database-client', $driver->backup($config, $path));
        $this->assertSame('fake-database-client', $driver->restore($config, $path));
    }

    public function test_postgres_driver_uses_configured_native_tools_for_streamed_round_trip(): void
    {
        $driver = app(PostgresDatabaseDriver::class);
        $path = $this->temporaryDirectory.'/postgres.sql';
        $config = $this->connectionConfig('pgsql');

        $this->assertTrue($driver->capabilities($config)['restore_available']);
        $this->assertSame('fake-database-client', $driver->backup($config, $path));
        $this->assertSame('fake-database-client', $driver->restore($config, $path));
    }

    public function test_postgres_clients_receive_the_configured_tls_certificates(): void
    {
        file_put_contents($this->binary, <<<'PHP'
            #!/usr/bin/env php
            <?php
            exit(getenv('PGSSLMODE') === 'verify-full'
                && getenv('PGSSLROOTCERT') === '/test/ca.pem'
                && getenv('PGSSLCERT') === '/test/client.pem'
                && getenv('PGSSLKEY') === '/test/client.key' ? 0 : 1);
            PHP);
        $config = array_replace($this->connectionConfig('pgsql'), [
            'sslmode' => 'verify-full',
            'sslrootcert' => '/test/ca.pem',
            'sslcert' => '/test/client.pem',
            'sslkey' => '/test/client.key',
        ]);
        $this->assertSame('fake-database-client', app(PostgresDatabaseDriver::class)->backup($config, $this->temporaryDirectory.'/tls.sql'));
    }

    public function test_versioned_binary_directory_patterns_are_resolved(): void
    {
        $versionedDirectory = $this->temporaryDirectory.'/mysql-8.4/bin';
        mkdir($versionedDirectory, 0700, true);
        $versionedBinary = $versionedDirectory.'/fake-database-client';
        copy($this->binary, $versionedBinary);
        chmod($versionedBinary, 0700);

        config([
            'tool.database.binary_directories' => [$this->temporaryDirectory.'/mysql-*/bin'],
        ]);

        try {
            $capabilities = app(MySqlDatabaseDriver::class)->capabilities($this->connectionConfig('mysql'));

            $this->assertTrue($capabilities['backup_available']);
            $this->assertTrue($capabilities['restore_available']);
        } finally {
            unlink($versionedBinary);
            rmdir($versionedDirectory);
            rmdir(dirname($versionedDirectory));
        }
    }

    /**
     * Build non-secret connection settings for command argument tests.
     */
    private function connectionConfig(string $driver): array
    {
        return [
            'driver' => $driver,
            'host' => '127.0.0.1',
            'port' => $driver === 'pgsql' ? '5432' : '3306',
            'database' => 'forkiva_test',
            'username' => 'forkiva',
            'password' => 'test-password',
            'sslmode' => 'prefer',
            'options' => [],
        ];
    }
}
