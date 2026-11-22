<?php

namespace App\Providers;

use AlternativeLaravelCache\Provider\AlternativeCacheStoresServiceProvider as BaseServiceProvider;
use App\Cache\AlternativeFileCacheStoreWithLocks;
use App\Cache\TaggableDatabaseStore;
use Illuminate\Cache\CacheManager;
use Illuminate\Contracts\Foundation\Application;
use League\Flysystem\Filesystem;

class AlternativeCacheStoresServiceProvider extends BaseServiceProvider
{
    /**
     * Register the corrected file driver after package cache drivers are loaded.
     */
    public function register(): void
    {
        $this->app->extend('cache', function (CacheManager $cacheManager) {
            $hasLocks = trait_exists('Illuminate\Cache\HasCacheLock');
            $this->addDatabaseCacheDriver($cacheManager);
            $this->addRedisCacheDriver($cacheManager, $hasLocks);
            $this->addMemcachedCacheDriver($cacheManager, $hasLocks);
            $this->addFileCacheDriver($cacheManager, $hasLocks);
            $this->addHierarchicalFileCacheDriver($cacheManager, $hasLocks);

            return $cacheManager;
        });
    }

    /**
     * Register the alternative database cache with Laravel-compatible atomic locks.
     */
    protected function addDatabaseCacheDriver(CacheManager $cacheManager): void
    {
        $cacheManager->extend('database', function (Application $app, array $config) use ($cacheManager) {
            $store = new TaggableDatabaseStore(
                $app['db']->connection($config['connection'] ?? null),
                $config['table'],
                $config['prefix'] ?? $app['config']['cache.prefix'],
                $config['lock_table'] ?? 'cache_locks',
                $config['lock_lottery'] ?? [2, 100],
                $config['lock_timeout'] ?? 86400,
                $config['serializable_classes'] ?? $app['config']['cache.serializable_classes'] ?? true,
            );
            $store->setLockConnection($app['db']->connection($config['lock_connection'] ?? $config['connection'] ?? null));

            return $cacheManager->repository($store, $config);
        });
    }

    /**
     * Register the alternative file cache with Laravel-compatible atomic locks.
     */
    protected function addFileCacheDriver(CacheManager $cacheManager, bool $hasLocks): void
    {
        // CacheManager rebinds custom driver closures to itself.
        $provider = $this;
        $cacheManager->extend(
            $this->fileDriverName,
            function (Application $app, array $cacheConfig) use ($cacheManager, $provider) {
                $store = new AlternativeFileCacheStoreWithLocks(
                    new Filesystem($provider->makeFileCacheAdapter($cacheConfig)),
                    $provider->getPrefix($cacheConfig),
                    $cacheConfig['path'],
                    $app['files'],
                    $cacheConfig['permission'] ?? null,
                    $app['config']['cache.serializable_classes'] ?? true,
                );

                $store->setLockDirectory($cacheConfig['lock_path'] ?? null);
                $store->setLogger($app->make('log'));

                return $cacheManager->repository($store, $cacheConfig);
            },
        );
    }
}
