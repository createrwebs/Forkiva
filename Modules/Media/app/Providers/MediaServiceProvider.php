<?php

namespace Modules\Media\Providers;

use Illuminate\Support\ServiceProvider;
use Modules\Media\Console\FixMediaTreeCommand;

class MediaServiceProvider extends ServiceProvider
{
    /**
     * Bootstrap the application services.
     */
    public function boot(): void
    {
        if ($this->app->runningInConsole()) {
            $this->commands(FixMediaTreeCommand::class);
        }
    }
}
