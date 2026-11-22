<?php

namespace Modules\Core\Providers;

use Illuminate\Contracts\Support\DeferrableProvider;
use Illuminate\Support\ServiceProvider;
use Modules\Core\Contracts\IdempotencyServiceInterface;
use Modules\Core\Contracts\ImmutableAuditLoggerInterface;
use Modules\Core\Services\Governance\IdempotencyService;
use Modules\Core\Services\Governance\ImmutableAuditLogger;

class DeferredCoreServiceProvider extends ServiceProvider implements DeferrableProvider
{
    /**
     * Boot the application events.
     */
    public function register(): void
    {
        $this->app->singleton(
            abstract: ImmutableAuditLoggerInterface::class,
            concrete: fn ($app) => $app->make(ImmutableAuditLogger::class)
        );

        $this->app->singleton(
            abstract: IdempotencyServiceInterface::class,
            concrete: fn ($app) => $app->make(IdempotencyService::class)
        );
    }

    /**
     * Get the services provided by the provider.
     */
    public function provides(): array
    {
        return [
            ImmutableAuditLoggerInterface::class,
            IdempotencyServiceInterface::class,
        ];
    }
}
