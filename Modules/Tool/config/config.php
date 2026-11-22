<?php

use Modules\User\Enums\PermissionAction as Action;

$configuredBinaryDirectories = preg_split(
    '/'.preg_quote(PATH_SEPARATOR, '/').'/',
    (string) env('DB_TOOL_BINARY_DIRECTORIES', ''),
    flags: PREG_SPLIT_NO_EMPTY,
) ?: [];

$programFiles = rtrim(str_replace('\\', '/', (string) env('ProgramFiles', 'C:/Program Files')), '/');

$platformBinaryDirectories = match (PHP_OS_FAMILY) {
    'Windows' => [
        'C:/xampp/mysql/bin',
        'C:/wamp64/bin/mysql/mysql*/bin',
        'C:/wamp64/bin/mariadb/mariadb*/bin',
        $programFiles.'/MySQL/MySQL Server */bin',
        $programFiles.'/MariaDB */bin',
        $programFiles.'/PostgreSQL/*/bin',
    ],
    'Darwin' => [
        '/Applications/XAMPP/xamppfiles/bin',
        '/Applications/Postgres.app/Contents/Versions/latest/bin',
        '/opt/homebrew/opt/mysql-client*/bin',
        '/opt/homebrew/opt/libpq/bin',
        '/opt/homebrew/opt/mariadb/bin',
        '/opt/homebrew/bin',
        '/usr/local/mysql/bin',
        '/usr/local/opt/mysql-client*/bin',
        '/usr/local/opt/libpq/bin',
        '/usr/local/opt/mariadb/bin',
        '/usr/local/pgsql/bin',
    ],
    default => [
        '/usr/bin',
        '/usr/local/bin',
        '/opt/mysql/bin',
        '/opt/mariadb/bin',
        '/opt/postgresql/bin',
        '/usr/lib/postgresql/*/bin',
    ],
};

return [
    'database' => [
        'backup_directory' => storage_path('app/backups/database'),
        'dump_binary' => env('DB_DUMP_BINARY'),
        'restore_binary' => env('DB_RESTORE_BINARY'),
        'binary_directories' => array_values(array_unique(array_filter([
            ...$configuredBinaryDirectories,
            env('DB_TOOL_BINARY_DIRECTORY'),
            ...$platformBinaryDirectories,
        ], fn ($directory) => is_string($directory) && $directory !== ''))),
        'process_timeout_seconds' => (int) env('DB_TOOL_PROCESS_TIMEOUT', 1800),
        'lock_seconds' => (int) env('DB_TOOL_LOCK_SECONDS', 3600),
        'max_upload_kb' => (int) env('DB_TOOL_MAX_UPLOAD_KB', 512000),
        'maintenance_during_restore' => (bool) env('DB_TOOL_MAINTENANCE_DURING_RESTORE', true),
        'pause_queue_during_restore' => (bool) env('DB_TOOL_PAUSE_QUEUE_DURING_RESTORE', true),
        'queue' => env('DB_TOOL_QUEUE', 'default'),
        'retention_count' => (int) env('DB_TOOL_RETENTION_COUNT', 20),
        'require_distributed_lock' => (bool) env('DB_TOOL_REQUIRE_DISTRIBUTED_LOCK', false),
    ],

    'permissions' => [
        'database_tools' => [
            Action::Index,
            Action::Backup,
            Action::Download,
            Action::Restore,
        ],
    ],
];
