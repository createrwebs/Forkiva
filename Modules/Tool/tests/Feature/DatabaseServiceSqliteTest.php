<?php

namespace Modules\Tool\Tests\Feature;

use Illuminate\Support\Facades\DB;
use Modules\Tool\Services\Database\DatabaseServiceInterface;
use SQLite3;
use Tests\TestCase;

class DatabaseServiceSqliteTest extends TestCase
{
    private string $temporaryDirectory;

    private string $databasePath;

    protected function setUp(): void
    {
        parent::setUp();

        if (! extension_loaded('sqlite3')) {
            $this->markTestSkipped('The SQLite3 extension is required.');
        }

        $this->temporaryDirectory = sys_get_temp_dir().'/forkiva_database_tool_'.uniqid();
        mkdir($this->temporaryDirectory, 0700, true);
        $this->databasePath = $this->temporaryDirectory.'/active.sqlite';

        $database = new SQLite3($this->databasePath);
        $database->exec('CREATE TABLE samples (id INTEGER PRIMARY KEY, value TEXT NOT NULL)');
        $database->exec("INSERT INTO samples (value) VALUES ('before-backup')");
        $database->exec(<<<'SQL'
            CREATE TABLE activity_log (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                log_name TEXT,
                description TEXT NOT NULL,
                subject_type TEXT,
                event TEXT,
                subject_id INTEGER,
                causer_type TEXT,
                causer_id INTEGER,
                properties TEXT,
                batch_uuid TEXT,
                created_at TEXT,
                updated_at TEXT
            )
            SQL);
        $database->close();

        config([
            'database.default' => 'tool_sqlite_test',
            'database.connections.tool_sqlite_test' => [
                'driver' => 'sqlite',
                'database' => $this->databasePath,
                'prefix' => '',
                'foreign_key_constraints' => true,
            ],
            'tool.database.backup_directory' => $this->temporaryDirectory.'/backups',
            'tool.database.retention_count' => 20,
            'tool.database.require_distributed_lock' => false,
        ]);

        DB::purge('tool_sqlite_test');
    }

    protected function tearDown(): void
    {
        DB::purge('tool_sqlite_test');
        $this->deleteDirectory($this->temporaryDirectory);

        parent::tearDown();
    }

    public function test_sqlite_backup_and_restore_round_trip_with_manifest(): void
    {
        $service = app(DatabaseServiceInterface::class);

        $index = $service->index();
        $this->assertSame('sqlite', $index['database']['driver']);
        $this->assertTrue($index['database']['backup_available']);
        $this->assertTrue($index['database']['restore_available']);

        $backupResult = $service->backup();
        $backup = $backupResult['backup'];
        $backupPath = $this->temporaryDirectory.'/backups/'.$backup['name'];

        $this->assertFileExists($backupPath);
        $this->assertFileExists($backupPath.'.json');
        $this->assertSame(hash_file('sha256', $backupPath), $backup['checksum_sha256']);
        $this->assertSame('sqlite', $backup['driver']);

        if (PHP_OS_FAMILY !== 'Windows') {
            $this->assertSame(0700, fileperms(dirname($backupPath)) & 0777);
            $this->assertSame(0600, fileperms($backupPath) & 0777);
            $this->assertSame(0600, fileperms($backupPath.'.json') & 0777);
        }

        DB::connection('tool_sqlite_test')->table('samples')->update(['value' => 'after-backup']);
        $service->restoreFromBackup($backup['name']);

        $this->assertSame(
            'before-backup',
            DB::connection('tool_sqlite_test')->table('samples')->value('value'),
        );

        $backups = $service->index()['backups'];
        $this->assertCount(2, $backups, 'Restore must retain the original and safety backups.');
    }

    public function test_index_excludes_partial_and_manifest_files(): void
    {
        mkdir($this->temporaryDirectory.'/backups', 0700, true);
        file_put_contents($this->temporaryDirectory.'/backups/backup.sqlite.partial', 'partial');
        file_put_contents($this->temporaryDirectory.'/backups/backup.sqlite.json', '{}');

        $this->assertSame([], app(DatabaseServiceInterface::class)->index()['backups']);
    }

    public function test_restore_rejects_a_managed_backup_with_a_changed_checksum(): void
    {
        $service = app(DatabaseServiceInterface::class);
        $backup = $service->backup()['backup'];
        $backupPath = $this->temporaryDirectory.'/backups/'.$backup['name'];
        file_put_contents($backupPath, 'tampered', FILE_APPEND);

        $this->expectExceptionMessage(__('tool::database.messages.backup_checksum_mismatch'));

        $service->restoreFromBackup($backup['name']);
    }

    public function test_backup_retention_removes_the_oldest_complete_backups(): void
    {
        config(['tool.database.retention_count' => 2]);
        $service = app(DatabaseServiceInterface::class);

        $service->backup();
        $service->backup();
        $service->backup();

        $this->assertCount(2, $service->index()['backups']);
        $this->assertCount(2, glob($this->temporaryDirectory.'/backups/*.json') ?: []);
    }

    public function test_preflight_blocks_operations_when_a_distributed_lock_is_required(): void
    {
        config(['tool.database.require_distributed_lock' => true]);
        $service = app(DatabaseServiceInterface::class);

        $this->assertFalse($service->index()['database']['preflight_ready']);
        $this->assertContains(
            'distributed_lock',
            $service->index()['database']['readiness_blockers'],
        );

        $this->expectExceptionMessage('distributed_lock');

        $service->backup();
    }

    public function test_preflight_rejects_database_backed_operation_locks(): void
    {
        config([
            'cache.default' => 'tool_database_cache',
            'cache.stores.tool_database_cache' => [
                'driver' => 'database',
                'connection' => 'tool_sqlite_test',
                'table' => 'cache',
            ],
        ]);

        $capabilities = app(DatabaseServiceInterface::class)->index()['database'];

        $this->assertFalse($capabilities['preflight_ready']);
        $this->assertContains('database_cache', $capabilities['readiness_blockers']);
    }

    private function deleteDirectory(string $directory): void
    {
        if (! is_dir($directory)) {
            return;
        }

        foreach (array_diff(scandir($directory) ?: [], ['.', '..']) as $entry) {
            $path = $directory.DIRECTORY_SEPARATOR.$entry;
            is_dir($path) ? $this->deleteDirectory($path) : unlink($path);
        }

        rmdir($directory);
    }
}
