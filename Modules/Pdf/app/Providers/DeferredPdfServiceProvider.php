<?php

namespace Modules\Pdf\Providers;

use Illuminate\Contracts\Support\DeferrableProvider;
use Illuminate\Support\ServiceProvider;
use Modules\Pdf\Services\Driver\PdfDriverConfigurator;
use Modules\Pdf\Services\Driver\PdfDriverVerifier;
use Modules\Pdf\Services\Driver\PdfDriverVerifierInterface;
use Modules\Pdf\Services\Generator\PdfGenerator;
use Modules\Pdf\Services\Generator\PdfGeneratorInterface;
use Modules\Pdf\Services\Settings\PdfSettingsManager;
use Modules\Pdf\Services\Settings\PdfSettingsManagerInterface;

class DeferredPdfServiceProvider extends ServiceProvider implements DeferrableProvider
{
    /**
     * Register PDF service bindings.
     */
    public function register(): void
    {
        $this->app->singleton(PdfDriverConfigurator::class);
        $this->app->singleton(PdfDriverVerifierInterface::class, PdfDriverVerifier::class);
        $this->app->singleton(PdfSettingsManagerInterface::class, PdfSettingsManager::class);
        $this->app->singleton(PdfGeneratorInterface::class, PdfGenerator::class);
    }

    /**
     * Get the services provided by the provider.
     *
     * @return list<class-string>
     */
    public function provides(): array
    {
        return [
            PdfDriverConfigurator::class,
            PdfDriverVerifierInterface::class,
            PdfSettingsManagerInterface::class,
            PdfGeneratorInterface::class,
        ];
    }
}
