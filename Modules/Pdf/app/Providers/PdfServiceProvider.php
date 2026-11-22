<?php

namespace Modules\Pdf\Providers;

use Illuminate\Support\ServiceProvider;
use Modules\Pdf\Console\DiagnosePdfCommand;

class PdfServiceProvider extends ServiceProvider
{
    /**
     * Bootstrap PDF views and console commands.
     */
    public function boot(): void
    {
        $root = dirname(__DIR__, 2);
        $this->loadViewsFrom($root.'/resources/views', 'pdf');

        if ($this->app->runningInConsole()) {
            $this->commands(DiagnosePdfCommand::class);
        }
    }
}
