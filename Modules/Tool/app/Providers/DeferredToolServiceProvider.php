<?php

namespace Modules\Tool\Providers;

use Illuminate\Contracts\Support\DeferrableProvider;
use Illuminate\Support\ServiceProvider;
use Modules\Tool\Services\Database\DatabaseBinaryLocator;
use Modules\Tool\Services\Database\DatabaseBinaryLocatorInterface;
use Modules\Tool\Services\Database\DatabaseRestoreGuard;
use Modules\Tool\Services\Database\DatabaseRestoreGuardInterface;
use Modules\Tool\Services\Database\DatabaseService;
use Modules\Tool\Services\Database\DatabaseServiceInterface;

class DeferredToolServiceProvider extends ServiceProvider implements DeferrableProvider
{
    /**
     * Register the application services.
     */
    public function register(): void
    {
        $this->app->singleton(
            abstract: DatabaseBinaryLocatorInterface::class,
            concrete: fn ($app) => $app->make(DatabaseBinaryLocator::class)
        );

        $this->app->singleton(
            abstract: DatabaseRestoreGuardInterface::class,
            concrete: fn ($app) => $app->make(DatabaseRestoreGuard::class)
        );

        $this->app->singleton(
            abstract: DatabaseServiceInterface::class,
            concrete: fn ($app) => $app->make(DatabaseService::class)
        );
    }

    /**
     * Resolve null binary overrides after module configuration is registered.
     */
    public function boot(DatabaseBinaryLocatorInterface $binaries): void
    {
        $this->app->booted(function () use ($binaries): void {
            $driver = (string) config('database.connections.'.config('database.default').'.driver');

            [$dumpCandidates, $restoreCandidates] = match ($driver) {
                'mysql', 'mariadb' => [
                    ['mysqldump', 'mariadb-dump'],
                    ['mysql', 'mariadb'],
                ],
                'pgsql' => [
                    ['pg_dump'],
                    ['psql'],
                ],
                default => [[], []],
            };

            if (blank(config('tool.database.dump_binary'))) {
                config()->set('tool.database.dump_binary', $binaries->find($dumpCandidates));
            }

            if (blank(config('tool.database.restore_binary'))) {
                config()->set('tool.database.restore_binary', $binaries->find($restoreCandidates));
            }
        });
    }

    /**
     * Get the services provided by the provider.
     */
    public function provides(): array
    {
        return [
            DatabaseBinaryLocatorInterface::class,
            DatabaseRestoreGuardInterface::class,
            DatabaseServiceInterface::class,
        ];
    }
}
