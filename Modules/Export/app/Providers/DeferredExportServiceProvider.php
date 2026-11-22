<?php

namespace Modules\Export\Providers;

use Illuminate\Contracts\Support\DeferrableProvider;
use Illuminate\Support\ServiceProvider;
use Modules\Export\Services\Export\ExportService;
use Modules\Export\Services\Export\ExportServiceInterface;
use Modules\Export\Services\ExportNotification\ExportNotificationService;
use Modules\Export\Services\ExportNotification\ExportNotificationServiceInterface;

class DeferredExportServiceProvider extends ServiceProvider implements DeferrableProvider
{
    /**
     * Register the export service bindings.
     */
    public function register(): void
    {
        $this->app->singleton(
            ExportServiceInterface::class,
            fn ($app) => $app->make(ExportService::class)
        );
        $this->app->singleton(
            ExportNotificationServiceInterface::class,
            fn ($app) => $app->make(ExportNotificationService::class)
        );
    }

    /**
     * Get the services provided by the provider.
     *
     * @return array<int, string>
     */
    public function provides(): array
    {
        return [
            ExportServiceInterface::class,
            ExportNotificationServiceInterface::class,
        ];
    }
}
