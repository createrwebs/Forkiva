<?php

namespace Modules\Category\Providers;

use Illuminate\Support\ServiceProvider;
use Modules\Category\Console\FixCategoryTreeCommand;

class CategoryServiceProvider extends ServiceProvider
{
    /**
     * Bootstrap the application services.
     */
    public function boot(): void
    {
        if ($this->app->runningInConsole()) {
            $this->commands(FixCategoryTreeCommand::class);
        }
    }
}
