<?php

namespace Modules\Service\Providers;

use Illuminate\Contracts\Support\DeferrableProvider;
use Illuminate\Support\ServiceProvider;
use Modules\Service\Services\ServiceRegistry\ServiceRegistryService;
use Modules\Service\Services\ServiceRegistry\ServiceRegistryServiceInterface;

class DeferredServiceServiceProvider extends ServiceProvider implements DeferrableProvider
{
    /**
     * Boot the application events.
     */
    public function register(): void
    {
        $this->app->singleton(
            abstract: ServiceRegistryServiceInterface::class,
            concrete: fn ($app) => $app->make(ServiceRegistryService::class)
        );
    }

    /**
     * Get the services provided by the provider.
     */
    public function provides(): array
    {
        return [
            ServiceRegistryServiceInterface::class,
        ];
    }
}
