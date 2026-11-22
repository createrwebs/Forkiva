<?php

/** Bootstrap the PostgreSQL gate only against an explicitly disposable database. */
require dirname(__DIR__).'/vendor/autoload.php';

$database = (string) ($_ENV['DB_DATABASE'] ?? getenv('DB_DATABASE'));
if (! str_ends_with($database, '_test')) {
    throw new RuntimeException('PostgreSQL tests require DB_DATABASE ending in _test. Never use application data.');
}
if (! extension_loaded('pdo_pgsql')) {
    throw new RuntimeException('The PostgreSQL test gate requires pdo_pgsql.');
}

$storage = sys_get_temp_dir().'/forkiva-pgsql-tests-'.bin2hex(random_bytes(8));
foreach (['framework/views', 'framework/sessions', 'framework/cache/data', 'logs', 'app/public'] as $directory) {
    mkdir($storage.'/'.$directory, 0700, true);
}
file_put_contents($storage.'/framework/installed.json', '{}');
// PDF tests read bundled fonts; generated files remain in disposable storage.
symlink(dirname(__DIR__).'/storage/fonts', $storage.'/fonts');
putenv('LARAVEL_STORAGE_PATH='.$storage);
$_ENV['LARAVEL_STORAGE_PATH'] = $_SERVER['LARAVEL_STORAGE_PATH'] = $storage;
$ownerPid = getmypid();
register_shutdown_function(static function () use ($storage, $ownerPid): void {
    // Forked concurrency workers must not delete their parent's test storage.
    if (getmypid() === $ownerPid) {
        (new Illuminate\Filesystem\Filesystem)->deleteDirectory($storage);
    }
});
