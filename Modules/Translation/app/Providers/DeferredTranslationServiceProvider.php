<?php

namespace Modules\Translation\Providers;

use Illuminate\Contracts\Support\DeferrableProvider;
use Illuminate\Support\ServiceProvider;
use Modules\Translation\Services\Translation\Export\TranslationExportService;
use Modules\Translation\Services\Translation\Export\TranslationExportServiceInterface;
use Modules\Translation\Services\Translation\Import\TranslationImportService;
use Modules\Translation\Services\Translation\Import\TranslationImportServiceInterface;
use Modules\Translation\Services\Translation\Support\TranslationBulkSupportService;
use Modules\Translation\Services\Translation\Support\TranslationBulkSupportServiceInterface;
use Modules\Translation\Services\Translation\TranslationService;
use Modules\Translation\Services\Translation\TranslationServiceInterface;

class DeferredTranslationServiceProvider extends ServiceProvider implements DeferrableProvider
{
    /**
     * Register the translation service bindings.
     */
    public function register(): void
    {
        $this->app->singleton(
            abstract: TranslationServiceInterface::class,
            concrete: fn ($app) => $app->make(TranslationService::class)
        );
        $this->app->singleton(
            abstract: TranslationBulkSupportServiceInterface::class,
            concrete: fn ($app) => $app->make(TranslationBulkSupportService::class)
        );
        $this->app->singleton(
            abstract: TranslationExportServiceInterface::class,
            concrete: fn ($app) => $app->make(TranslationExportService::class)
        );
        $this->app->singleton(
            abstract: TranslationImportServiceInterface::class,
            concrete: fn ($app) => $app->make(TranslationImportService::class)
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
            TranslationServiceInterface::class,
            TranslationBulkSupportServiceInterface::class,
            TranslationExportServiceInterface::class,
            TranslationImportServiceInterface::class,
        ];
    }
}
