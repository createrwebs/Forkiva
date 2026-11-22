<?php

namespace Modules\Branch\Providers;

use Illuminate\Contracts\Support\DeferrableProvider;
use Illuminate\Support\ServiceProvider;
use Modules\Branch\Services\Branch\BranchService;
use Modules\Branch\Services\Branch\BranchServiceInterface;
use Modules\Branch\Services\BranchAccess\BranchAccessService;
use Modules\Branch\Services\BranchAccess\BranchAccessServiceInterface;

class DeferredBranchServiceProvider extends ServiceProvider implements DeferrableProvider
{
    /**
     * Boot the application events.
     */
    public function register(): void
    {
        $this->app->singleton(
            abstract: BranchServiceInterface::class,
            concrete: fn ($app) => $app->make(BranchService::class)
        );
        $this->app->singleton(
            abstract: BranchAccessServiceInterface::class,
            concrete: fn ($app) => $app->make(BranchAccessService::class)
        );
    }

    /**
     * Get the services provided by the provider.
     */
    public function provides(): array
    {
        return [
            BranchServiceInterface::class,
            BranchAccessServiceInterface::class,
        ];
    }
}
