<?php

namespace Modules\Installer\Tests\Unit;

use Illuminate\Contracts\Http\Kernel;
use Illuminate\Foundation\Application;
use Illuminate\Http\Request;
use Modules\Installer\Providers\DeferredInstallerServiceProvider;
use Modules\Installer\Services\Checks\InstallerChecks;
use Modules\Installer\Services\Checks\InstallerChecksInterface;
use Modules\Installer\Services\Configuration\InstallerConfiguration;
use Modules\Installer\Services\Configuration\InstallerConfigurationInterface;
use Modules\Installer\Services\DatabaseProbe\InstallerDatabaseProbe;
use Modules\Installer\Services\DatabaseProbe\InstallerDatabaseProbeInterface;
use Modules\Installer\Services\Dependencies\InstallerDependencies;
use Modules\Installer\Services\Dependencies\InstallerDependenciesInterface;
use Modules\Installer\Services\Environment\InstallerEnvironment;
use Modules\Installer\Services\Environment\InstallerEnvironmentInterface;
use Modules\Installer\Services\Installation\InstallationService;
use Modules\Installer\Services\Installation\InstallationServiceInterface;
use Modules\Installer\Services\State\InstallerState;
use Modules\Installer\Services\State\InstallerStateInterface;
use Tests\TestCase;

class InstallerServiceProviderTest extends TestCase
{
    /**
     * Bootstrap HTTP services because the console kernel eagerly loads deferred providers.
     */
    public function createApplication(): Application
    {
        $app = require dirname(__DIR__, 4).'/bootstrap/app.php';
        $app->instance('request', Request::create('http://localhost/installer'));
        $app->make(Kernel::class)->bootstrap();

        return $app;
    }

    /**
     * Resolve every installer contract lazily as a singleton through module registration.
     */
    public function test_installation_service_is_deferred_until_its_contract_is_resolved(): void
    {
        $services = [
            InstallationServiceInterface::class => InstallationService::class,
            InstallerChecksInterface::class => InstallerChecks::class,
            InstallerConfigurationInterface::class => InstallerConfiguration::class,
            InstallerDatabaseProbeInterface::class => InstallerDatabaseProbe::class,
            InstallerDependenciesInterface::class => InstallerDependencies::class,
            InstallerEnvironmentInterface::class => InstallerEnvironment::class,
            InstallerStateInterface::class => InstallerState::class,
        ];

        foreach ($services as $contract => $implementation) {
            $this->assertTrue(app()->isDeferredService($contract));
            $this->assertFalse(app()->resolved($contract));
        }
        $this->assertNull(app()->getProvider(DeferredInstallerServiceProvider::class));

        foreach ($services as $contract => $implementation) {
            $service = app($contract);
            $this->assertInstanceOf($implementation, $service);
            $this->assertSame($service, app($contract));
            $this->assertTrue(app()->resolved($contract));
        }
        $this->assertInstanceOf(DeferredInstallerServiceProvider::class, app()->getProvider(DeferredInstallerServiceProvider::class));
        $this->assertFalse(app()->isDeferredService(InstallationServiceInterface::class));
    }
}
