<?php

namespace Modules\Import\Providers;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Modules\Import\Console\CleanupImportsCommand;

class ImportServiceProvider extends ServiceProvider
{
    /**
     * Register cleanup on one scheduler instance to avoid duplicate file work.
     */
    public function boot(): void
    {
        RateLimiter::for('import-batches', fn (Request $request) => Limit::perMinute(120)
            ->by($request->user()?->id ?: $request->ip()));

        if ($this->app->runningInConsole()) {
            $this->commands([
                CleanupImportsCommand::class,
            ]);
            $this->app->booted(function ($app): void {
                $app[Schedule::class]
                    ->command(CleanupImportsCommand::class)
                    ->dailyAt('02:30')
                    ->withoutOverlapping()
                    ->onOneServer();
            });
        }
    }
}
