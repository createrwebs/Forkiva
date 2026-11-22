<?php

namespace Modules\Tool\Services\Database;

use Carbon\Carbon;
use Closure;
use Illuminate\Database\ConfigurationUrlParser;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Modules\Tool\Services\Database\Drivers\DatabaseDriverFactory;
use Modules\Tool\Services\Database\Drivers\DatabaseDriverInterface;
use RuntimeException;
use Spatie\Activitylog\Models\Activity;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Throwable;

class DatabaseService implements DatabaseServiceInterface
{
    public function __construct(
        private readonly DatabaseDriverFactory $drivers,
        private readonly DatabaseRestoreGuardInterface $restoreGuard,
    ) {}

    /** {@inheritDoc} */
    public function index(): array
    {
        $config = $this->getDatabaseConfig();
        $driver = $this->drivers->make($config['driver']);

        return [
            'database' => $this->databaseCapabilities($config, $driver),
            'backups' => $this->getBackups(),
        ];
    }

    /** {@inheritDoc} */
    public function backup(): array
    {
        return $this->withOperationLock(function (): array {
            $config = $this->getDatabaseConfig();
            $driver = $this->drivers->make($config['driver']);
            $this->assertPreflightReady($config, $driver);
            $backup = $this->createBackup($config, $driver);
            $pruned = $this->pruneBackups([$backup['meta']['name']]);

            $this->logAction('backup', [
                'engine' => $backup['engine'],
                'driver' => $config['driver'],
                'backup' => $backup['meta'],
                'pruned_backups' => $pruned,
            ]);

            return [
                'backup' => $backup['meta'],
                'backups' => $this->getBackups(),
            ];
        });
    }

    /** {@inheritDoc} */
    public function restore(UploadedFile $file): array
    {
        $path = $file->getRealPath();
        if ($path === false) {
            throw new RuntimeException(__('tool::database.messages.backup_not_readable'));
        }

        return $this->restorePath(
            path: $path,
            displayName: $file->getClientOriginalName(),
            size: (int) $file->getSize(),
            fromBackup: false,
        );
    }

    /** {@inheritDoc} */
    public function restoreFromBackup(string $fileName): array
    {
        $path = $this->resolveBackupPath($fileName);

        return $this->restorePath(
            path: $path,
            displayName: basename($path),
            size: File::size($path),
            fromBackup: true,
        );
    }

    /** {@inheritDoc} */
    public function download(string $fileName): BinaryFileResponse
    {
        $path = $this->resolveBackupPath($fileName);

        $this->logAction('download', [
            'file_name' => basename($path),
            'size' => File::size($path),
        ]);

        return response()->download($path, basename($path), [
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    /**
     * Restore a validated path while preventing concurrent database operations.
     */
    private function restorePath(string $path, string $displayName, int $size, bool $fromBackup): array
    {
        return $this->withOperationLock(function () use ($path, $displayName, $size, $fromBackup): array {
            $config = $this->getDatabaseConfig();
            $driver = $this->drivers->make($config['driver']);
            $capabilities = $driver->capabilities($config);

            $this->assertPreflightReady($config, $driver);

            if (! $capabilities['restore_available']) {
                throw new RuntimeException($this->capabilityError($capabilities));
            }

            if ($fromBackup) {
                $this->assertBackupDriverMatches($path, $config['driver']);
                $this->assertBackupIntegrity($path);
            }

            [$safetyBackup, $engine] = $this->restoreGuard->run(function () use ($config, $driver, $path, $displayName): array {
                // A recoverable snapshot is mandatory before any destructive restore.
                $safetyBackup = $this->createBackup($config, $driver);

                try {
                    $engine = $driver->restore($config, $path);
                } catch (Throwable $exception) {
                    Log::critical('Database restore failed after creating a safety backup.', [
                        'driver' => $config['driver'],
                        'file_name' => $displayName,
                        'safety_backup' => $safetyBackup['meta']['name'],
                        'exception' => $exception,
                    ]);

                    throw $exception;
                }

                return [$safetyBackup, $engine];
            });

            $pruned = $this->pruneBackups([$safetyBackup['meta']['name']]);

            $this->logAction('restored', [
                'engine' => $engine,
                'driver' => $config['driver'],
                'file_name' => $displayName,
                'size' => $size,
                'from_backup' => $fromBackup,
                'safety_backup' => $safetyBackup['meta'],
                'pruned_backups' => $pruned,
            ]);

            return [
                'restored_file' => $displayName,
                'safety_backup' => $safetyBackup['meta'],
                'backups' => $this->getBackups(),
            ];
        });
    }

    /**
     * Create an atomic backup file and its integrity manifest.
     *
     * @return array{engine: string, meta: array<string, mixed>}
     */
    private function createBackup(array $config, DatabaseDriverInterface $driver): array
    {
        $capabilities = $driver->capabilities($config);
        if (! $capabilities['backup_available']) {
            throw new RuntimeException($this->capabilityError($capabilities));
        }

        $directory = $this->backupDirectory();
        File::ensureDirectoryExists($directory, 0700, true);
        $this->securePermissions($directory, 0700);

        $filename = sprintf(
            'backup_%s_%s_%s.%s',
            $config['driver'],
            now()->format('Y_m_d_His_u'),
            Str::lower(Str::random(10)),
            $driver->extension(),
        );
        $finalPath = $directory.DIRECTORY_SEPARATOR.$filename;
        $partialPath = $finalPath.'.partial';

        try {
            if (File::put($partialPath, '', true) === false) {
                throw new RuntimeException(__('tool::database.messages.backup_finalize_failed'));
            }
            $this->securePermissions($partialPath, 0600);

            $engine = $driver->backup($config, $partialPath);

            if (! File::exists($partialPath) || File::size($partialPath) === 0) {
                throw new RuntimeException(__('tool::database.messages.empty_backup_created'));
            }

            if (! File::move($partialPath, $finalPath)) {
                throw new RuntimeException(__('tool::database.messages.backup_finalize_failed'));
            }
            $this->securePermissions($finalPath, 0600);

            $manifest = [
                'version' => 1,
                'driver' => $config['driver'],
                'format' => $driver->extension(),
                'engine' => $engine,
                'checksum_sha256' => hash_file('sha256', $finalPath),
                'created_at' => now()->toIso8601String(),
                'app_version' => (string) config('app.version', ''),
            ];

            $manifestPath = $this->manifestPath($finalPath);
            if (File::put($manifestPath, '', true) === false) {
                throw new RuntimeException(__('tool::database.messages.backup_finalize_failed'));
            }
            $this->securePermissions($manifestPath, 0600);

            File::put(
                $manifestPath,
                json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR),
                true,
            );
            $this->securePermissions($manifestPath, 0600);
        } catch (Throwable $exception) {
            File::delete([$partialPath, $finalPath, $this->manifestPath($finalPath)]);

            throw $exception;
        }

        return [
            'engine' => $engine,
            'meta' => $this->backupMeta($finalPath),
        ];
    }

    /**
     * Get active connection config, including DB_URL overrides.
     */
    private function getDatabaseConfig(): array
    {
        $connection = (string) config('database.default');
        $config = config("database.connections.$connection");

        if (! is_array($config)) {
            throw new RuntimeException(__('tool::database.messages.invalid_connection'));
        }

        $config = (new ConfigurationUrlParser)->parseConfiguration($config);
        $driver = (string) ($config['driver'] ?? '');

        return array_merge($config, [
            'connection' => $connection,
            'driver' => $driver,
            'host' => (string) ($config['host'] ?? '127.0.0.1'),
            'port' => (string) ($config['port'] ?? ($driver === 'pgsql' ? '5432' : '3306')),
            'database' => (string) ($config['database'] ?? ''),
            'username' => (string) ($config['username'] ?? ''),
            'password' => (string) ($config['password'] ?? ''),
        ]);
    }

    /**
     * Return UI-safe capability information without credentials.
     */
    private function databaseCapabilities(array $config, DatabaseDriverInterface $driver): array
    {
        $capabilities = $driver->capabilities($config);
        $cacheStore = (string) config('cache.default');
        $cacheDriver = (string) config("cache.stores.$cacheStore.driver");
        $distributedLock = in_array($cacheDriver, ['redis', 'memcached', 'dynamodb'], true);
        $readinessBlockers = [];

        if (! $capabilities['backup_available'] || ! $capabilities['restore_available']) {
            $readinessBlockers[] = 'database_tools';
        }
        if (config('tool.database.require_distributed_lock', false) && ! $distributedLock) {
            $readinessBlockers[] = 'distributed_lock';
        }
        if ($cacheDriver === 'database') {
            $readinessBlockers[] = 'database_cache';
        }
        if (! $this->backupDirectoryIsWritable()) {
            $readinessBlockers[] = 'backup_storage';
        }
        if (! config('tool.database.maintenance_during_restore', true)) {
            $readinessBlockers[] = 'maintenance_guard';
        }
        if (! config('tool.database.pause_queue_during_restore', true)) {
            $readinessBlockers[] = 'queue_guard';
        }

        return array_merge($capabilities, [
            'driver' => $config['driver'],
            'supported' => true,
            'max_upload_mb' => (int) ceil((int) config('tool.database.max_upload_kb', 512000) / 1024),
            'safety_backup_required' => true,
            'maintenance_guard' => (bool) config('tool.database.maintenance_during_restore', true),
            'queue_guard' => (bool) config('tool.database.pause_queue_during_restore', true),
            'lock_scope' => $distributedLock ? 'distributed' : 'single_node',
            'retention_count' => (int) config('tool.database.retention_count', 20),
            'preflight_ready' => $readinessBlockers === [],
            'readiness_blockers' => $readinessBlockers,
        ]);
    }

    /**
     * Block operations when the configured production prerequisites fail.
     */
    private function assertPreflightReady(array $config, DatabaseDriverInterface $driver): void
    {
        $capabilities = $this->databaseCapabilities($config, $driver);

        if (! $capabilities['preflight_ready']) {
            throw new RuntimeException(__('tool::database.messages.preflight_failed', [
                'blockers' => implode(', ', $capabilities['readiness_blockers']),
            ]));
        }
    }

    private function capabilityError(array $capabilities): string
    {
        if ($capabilities['missing_tools'] !== []) {
            return __('tool::database.messages.missing_tools', [
                'tools' => implode(', ', $capabilities['missing_tools']),
            ]);
        }

        return __('tool::database.messages.operation_unavailable');
    }

    /**
     * Execute one backup or restore at a time across processes.
     */
    private function withOperationLock(Closure $operation): mixed
    {
        $lock = Cache::lock(
            'tool:database:operation',
            (int) config('tool.database.lock_seconds', 3600),
        );

        if (! $lock->get()) {
            throw new RuntimeException(__('tool::database.messages.operation_in_progress'));
        }

        try {
            return $operation();
        } finally {
            $lock->release();
        }
    }

    /**
     * Get complete backup files sorted latest first.
     */
    private function getBackups(): array
    {
        $directory = $this->backupDirectory();

        if (! File::isDirectory($directory)) {
            return [];
        }

        return collect(File::files($directory))
            ->filter(fn ($file) => $this->isBackupFile($file->getFilename()))
            ->sortByDesc(fn ($file) => $file->getFilename())
            ->values()
            ->map(fn ($file) => $this->backupMeta($file->getPathname()))
            ->all();
    }

    private function backupDirectory(): string
    {
        return (string) config('tool.database.backup_directory', storage_path('app/backups/database'));
    }

    /**
     * Determine whether the backup directory, or its nearest existing parent, is writable.
     */
    private function backupDirectoryIsWritable(): bool
    {
        $path = $this->backupDirectory();

        while (! File::exists($path)) {
            $parent = dirname($path);
            if ($parent === $path) {
                return false;
            }

            $path = $parent;
        }

        return File::isDirectory($path) && is_writable($path);
    }

    /**
     * Apply owner-only permissions on POSIX systems.
     */
    private function securePermissions(string $path, int $mode): void
    {
        if (PHP_OS_FAMILY === 'Windows') {
            return;
        }

        if (! @chmod($path, $mode)) {
            throw new RuntimeException(__('tool::database.messages.backup_permissions_failed'));
        }
    }

    /**
     * Keep the newest configured number of backups and their manifests.
     *
     * @param  array<int, string>  $protectedNames
     * @return array<int, string>
     */
    private function pruneBackups(array $protectedNames): array
    {
        $retention = max(0, (int) config('tool.database.retention_count', 20));
        if ($retention === 0) {
            return [];
        }

        $directory = $this->backupDirectory();
        if (! File::isDirectory($directory)) {
            return [];
        }

        $kept = 0;
        $deleted = [];
        $protected = array_fill_keys($protectedNames, true);
        $files = collect(File::files($directory))
            ->filter(fn ($file) => $this->isBackupFile($file->getFilename()))
            ->sortByDesc(fn ($file) => $file->getFilename())
            ->values();

        foreach ($files as $file) {
            $name = $file->getFilename();
            if ($kept < $retention || isset($protected[$name])) {
                $kept++;

                continue;
            }

            if (File::delete([$file->getPathname(), $this->manifestPath($file->getPathname())])) {
                $deleted[] = $name;
            }
        }

        return $deleted;
    }

    /**
     * Build backup metadata from the file and optional manifest.
     */
    private function backupMeta(string $path): array
    {
        $timestamp = File::lastModified($path);
        $manifest = $this->readManifest($path);

        return [
            'name' => basename($path),
            'size' => File::size($path),
            'size_human' => humanFileSize(File::size($path)),
            'created_at' => dateTimeFormat(Carbon::createFromTimestamp($timestamp)),
            'driver' => $manifest['driver'] ?? $this->inferDriverFromFilename(basename($path)),
            'format' => $manifest['format'] ?? pathinfo($path, PATHINFO_EXTENSION),
            'checksum_sha256' => $manifest['checksum_sha256'] ?? null,
            'engine' => $manifest['engine'] ?? null,
        ];
    }

    /**
     * Resolve a backup path with strict filename and containment checks.
     */
    private function resolveBackupPath(string $fileName): string
    {
        $name = basename($fileName);
        if ($name !== $fileName || ! $this->isBackupFile($name)) {
            throw new RuntimeException(__('tool::database.messages.invalid_backup_file'));
        }

        $path = $this->backupDirectory().DIRECTORY_SEPARATOR.$name;
        if (! File::isFile($path) || is_link($path)) {
            throw new RuntimeException(__('tool::database.messages.backup_not_found'));
        }

        $directory = realpath($this->backupDirectory());
        $resolved = realpath($path);
        if ($directory === false || $resolved === false || ! str_starts_with($resolved, $directory.DIRECTORY_SEPARATOR)) {
            throw new RuntimeException(__('tool::database.messages.invalid_backup_file'));
        }

        return $resolved;
    }

    private function isBackupFile(string $name): bool
    {
        return preg_match('/^[A-Za-z0-9._-]+\.(sql|sqlite|sqlite3|db)$/i', $name) === 1;
    }

    private function manifestPath(string $backupPath): string
    {
        return $backupPath.'.json';
    }

    /**
     * Read a valid backup manifest when present.
     */
    private function readManifest(string $backupPath): array
    {
        $path = $this->manifestPath($backupPath);
        if (! File::isFile($path)) {
            return [];
        }

        try {
            $manifest = json_decode(File::get($path), true, flags: JSON_THROW_ON_ERROR);

            return is_array($manifest) ? $manifest : [];
        } catch (Throwable) {
            return [];
        }
    }

    /**
     * Reject a saved backup from another engine family.
     */
    private function assertBackupDriverMatches(string $path, string $activeDriver): void
    {
        $backupDriver = $this->readManifest($path)['driver'] ?? null;
        if (! is_string($backupDriver) || $backupDriver === '') {
            return;
        }

        $normalize = fn (string $driver) => in_array($driver, ['mysql', 'mariadb'], true) ? 'mysql' : $driver;
        if ($normalize($backupDriver) !== $normalize($activeDriver)) {
            throw new RuntimeException(__('tool::database.messages.backup_driver_mismatch'));
        }
    }

    /**
     * Verify a managed backup has not changed since it was finalized.
     */
    private function assertBackupIntegrity(string $path): void
    {
        $checksum = $this->readManifest($path)['checksum_sha256'] ?? null;
        if (! is_string($checksum) || $checksum === '') {
            return;
        }

        if (! hash_equals($checksum, hash_file('sha256', $path))) {
            throw new RuntimeException(__('tool::database.messages.backup_checksum_mismatch'));
        }
    }

    private function inferDriverFromFilename(string $name): ?string
    {
        foreach (['mariadb', 'mysql', 'pgsql', 'sqlite'] as $driver) {
            if (str_starts_with($name, "backup_{$driver}_")) {
                return $driver;
            }
        }

        return null;
    }

    /**
     * Store an activity log for backup and restore operations.
     */
    private function logAction(string $event, array $details = []): void
    {
        $activity = new Activity;

        if (Auth::check()) {
            $activity->causer_id = Auth::id();
            $activity->causer_type = get_class(Auth::user());
        }

        $activity->log_name = 'database_tools';
        $activity->event = $event;
        $activity->description = 'tool::database.database';
        $activity->batch_uuid = (string) Str::uuid();
        $activity->properties = [
            'details' => $details,
            'info' => [
                'ip' => request()->ip(),
                'http_method' => request()->method(),
                'user_agent' => request()->header('User-Agent'),
            ],
            'trans_params' => [
                'resource' => __('tool::database.database'),
            ],
        ];

        $activity->save();
    }
}
