<?php

namespace Modules\Installer\Providers;

use Illuminate\Contracts\Support\DeferrableProvider;
use Illuminate\Support\ServiceProvider;
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

class DeferredInstallerServiceProvider extends ServiceProvider implements DeferrableProvider
{
    /**
     * Register installer services when one of their contracts is first resolved.
     */
    public function register(): void
    {
        foreach ($this->bindings() as $contract => $implementation) {
            $this->app->singleton(
                abstract: $contract,
                concrete: fn ($app) => $app->make($implementation)
            );
        }
    }

    /**
     * Get the service contracts provided by this deferred provider.
     *
     * @return list<class-string>
     */
    public function provides(): array
    {
        return array_keys($this->bindings());
    }

    /**
     * Map each installer contract to its concrete implementation.
     *
     * @return array<class-string, class-string>
     */
    private function bindings(): array
    {
        return [
            InstallationServiceInterface::class => InstallationService::class,
            InstallerChecksInterface::class => InstallerChecks::class,
            InstallerConfigurationInterface::class => InstallerConfiguration::class,
            InstallerDatabaseProbeInterface::class => InstallerDatabaseProbe::class,
            InstallerDependenciesInterface::class => InstallerDependencies::class,
            InstallerEnvironmentInterface::class => InstallerEnvironment::class,
            InstallerStateInterface::class => InstallerState::class,
        ];
    }
}
