<?php

namespace Modules\Export\Providers;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\ServiceProvider;
use Modules\Export\Console\CleanupExportsCommand;
use Modules\Export\Support\ExportSourceResolver;
use Modules\Export\Support\ExportValueNormalizer;

class ExportServiceProvider extends ServiceProvider
{
    /**
     * Register bindings.
     */
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../../config/config.php', 'export');

        $this->app->singleton(ExportSourceResolver::class);
        $this->app->singleton(ExportValueNormalizer::class);
    }

    /**
     * Bootstrap services.
     */
    public function boot(): void
    {
        if ($this->app->runningInConsole()) {
            $this->commands([
                CleanupExportsCommand::class,
            ]);
            $this->registerScheduler();
        }
    }

    /**
     * Register scheduled module tasks.
     */
    private function registerScheduler(): void
    {
        $this->app->booted(function ($app) {
            $app[Schedule::class]
                ->command(CleanupExportsCommand::class)
                ->dailyAt('02:00')
                ->withoutOverlapping()
                ->onOneServer();
        });
    }
}
