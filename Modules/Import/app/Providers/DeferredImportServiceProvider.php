<?php

namespace Modules\Import\Providers;

use Illuminate\Contracts\Support\DeferrableProvider;
use Illuminate\Support\ServiceProvider;
use Modules\Import\Services\Import\ImportService;
use Modules\Import\Services\Import\ImportServiceInterface;

class DeferredImportServiceProvider extends ServiceProvider implements DeferrableProvider
{
    public function register(): void
    {
        $this->app->singleton(
            ImportServiceInterface::class,
            fn ($app) => $app->make(ImportService::class)
        );
    }

    /** @return array<int, string> */
    public function provides(): array
    {
        return [ImportServiceInterface::class];
    }
}
