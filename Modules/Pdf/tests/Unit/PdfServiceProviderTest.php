<?php

namespace Modules\Pdf\Tests\Unit;

use Modules\Pdf\Providers\DeferredPdfServiceProvider;
use Modules\Pdf\Providers\PdfServiceProvider;
use Modules\Pdf\Services\Driver\PdfDriverConfigurator;
use Modules\Pdf\Services\Driver\PdfDriverVerifier;
use Modules\Pdf\Services\Driver\PdfDriverVerifierInterface;
use Modules\Pdf\Services\Generator\PdfGenerator;
use Modules\Pdf\Services\Generator\PdfGeneratorInterface;
use Modules\Pdf\Services\Settings\PdfSettingsManager;
use Modules\Pdf\Services\Settings\PdfSettingsManagerInterface;
use Tests\TestCase;

class PdfServiceProviderTest extends TestCase
{
    /**
     * Ensure shared module registration loads PDF configuration.
     */
    public function test_shared_module_registration_loads_pdf_configuration(): void
    {
        $this->assertSame('dompdf', config('pdf.default_driver'));
    }

    /**
     * Ensure the module declares its boot and deferred service providers.
     */
    public function test_module_declares_boot_and_deferred_service_providers(): void
    {
        $module = json_decode(
            file_get_contents(module_path('Pdf', 'module.json')),
            true,
            flags: JSON_THROW_ON_ERROR,
        );

        $this->assertSame([
            PdfServiceProvider::class,
            DeferredPdfServiceProvider::class,
        ], $module['providers']);
    }

    /**
     * Ensure deferred PDF abstractions resolve to singleton implementations.
     */
    public function test_deferred_pdf_services_resolve_as_singletons(): void
    {
        $services = [
            PdfDriverVerifierInterface::class => PdfDriverVerifier::class,
            PdfSettingsManagerInterface::class => PdfSettingsManager::class,
            PdfGeneratorInterface::class => PdfGenerator::class,
        ];

        foreach ($services as $abstract => $implementation) {
            $resolved = app($abstract);

            $this->assertInstanceOf($implementation, $resolved);
            $this->assertSame($resolved, app($abstract));
        }

        $configurator = app(PdfDriverConfigurator::class);

        $this->assertSame($configurator, app(PdfDriverConfigurator::class));
    }

    /**
     * Ensure every deferred binding is advertised by the provider.
     */
    public function test_deferred_provider_advertises_all_pdf_services(): void
    {
        $provider = new DeferredPdfServiceProvider(app());

        $this->assertSame([
            PdfDriverConfigurator::class,
            PdfDriverVerifierInterface::class,
            PdfSettingsManagerInterface::class,
            PdfGeneratorInterface::class,
        ], $provider->provides());
    }
}
