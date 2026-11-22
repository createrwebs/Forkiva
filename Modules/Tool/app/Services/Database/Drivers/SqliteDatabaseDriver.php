<?php

namespace Modules\Tool\Services\Database\Drivers;

use Illuminate\Support\Facades\DB;
use RuntimeException;
use SQLite3;
use Throwable;

class SqliteDatabaseDriver implements DatabaseDriverInterface
{
    /** {@inheritDoc} */
    public function extension(): string
    {
        return 'sqlite';
    }

    /** {@inheritDoc} */
    public function capabilities(array $config): array
    {
        $database = (string) ($config['database'] ?? '');
        $available = extension_loaded('sqlite3') && $database !== '' && $database !== ':memory:';

        return [
            'backup_available' => $available && is_file($database) && is_readable($database),
            'restore_available' => $available && is_file($database) && is_writable($database),
            'missing_tools' => extension_loaded('sqlite3') ? [] : ['php-sqlite3'],
            'format' => 'sqlite',
        ];
    }

    /** {@inheritDoc} */
    public function backup(array $config, string $destination): string
    {
        $database = $this->databasePath($config);
        $source = new SQLite3($database, SQLITE3_OPEN_READONLY);
        $target = new SQLite3($destination, SQLITE3_OPEN_READWRITE | SQLITE3_OPEN_CREATE);

        try {
            $source->busyTimeout(30000);
            $target->busyTimeout(30000);

            if (! $source->backup($target)) {
                throw new RuntimeException(__('tool::database.messages.sqlite_backup_failed'));
            }

            $this->assertIntegrity($target);
        } finally {
            $target->close();
            $source->close();
        }

        return 'sqlite-online-backup';
    }

    /** {@inheritDoc} */
    public function restore(array $config, string $source): string
    {
        $this->validateSqliteFile($source);
        $database = $this->databasePath($config);
        $connection = (string) ($config['connection'] ?? config('database.default'));

        DB::purge($connection);

        $backup = new SQLite3($source, SQLITE3_OPEN_READONLY);
        $target = new SQLite3($database, SQLITE3_OPEN_READWRITE);

        try {
            $backup->busyTimeout(30000);
            $target->busyTimeout(30000);
            $this->assertIntegrity($backup);

            if (! $backup->backup($target)) {
                throw new RuntimeException(__('tool::database.messages.sqlite_restore_failed'));
            }

            $this->assertIntegrity($target);
        } finally {
            $target->close();
            $backup->close();
            DB::purge($connection);
        }

        return 'sqlite-online-backup';
    }

    private function databasePath(array $config): string
    {
        if (! extension_loaded('sqlite3')) {
            throw new RuntimeException(__('tool::database.messages.sqlite_extension_required'));
        }

        $database = (string) ($config['database'] ?? '');
        if ($database === '' || $database === ':memory:') {
            throw new RuntimeException(__('tool::database.messages.sqlite_file_required'));
        }
        if (! is_file($database)) {
            throw new RuntimeException(__('tool::database.messages.sqlite_database_not_found'));
        }

        return $database;
    }

    private function validateSqliteFile(string $source): void
    {
        $stream = fopen($source, 'rb');
        $header = $stream === false ? false : fread($stream, 16);
        if (is_resource($stream)) {
            fclose($stream);
        }

        if ($header !== "SQLite format 3\0") {
            throw new RuntimeException(__('tool::database.messages.backup_driver_mismatch'));
        }
    }

    private function assertIntegrity(SQLite3 $database): void
    {
        try {
            $result = $database->querySingle('PRAGMA integrity_check');
        } catch (Throwable $exception) {
            throw new RuntimeException(__('tool::database.messages.sqlite_integrity_failed'), previous: $exception);
        }

        if ($result !== 'ok') {
            throw new RuntimeException(__('tool::database.messages.sqlite_integrity_failed'));
        }
    }
}
