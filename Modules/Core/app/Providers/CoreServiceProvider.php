<?php

namespace Modules\Core\Providers;

use App\Forkiva;
use Illuminate\Support\ServiceProvider;
use Modules\Core\Console\UpgradeCommand;
use Modules\Core\Contracts\DeliveryFeatureInterface;
use Modules\Core\Contracts\FinancialAccountLookupInterface;
use Modules\Core\Contracts\FinancialExpenseSummaryReaderInterface;
use Modules\Core\Contracts\FinancialInventoryBalanceReaderInterface;
use Modules\Core\Contracts\FinancialPostingBridgeInterface;
use Modules\Core\Contracts\FinancialPostingStatusReaderInterface;
use Modules\Core\Services\Accounting\NullFinancialAccountLookup;
use Modules\Core\Services\Accounting\NullFinancialExpenseSummaryReader;
use Modules\Core\Services\Accounting\NullFinancialInventoryBalanceReader;
use Modules\Core\Services\Accounting\NullFinancialPostingBridge;
use Modules\Core\Services\Accounting\NullFinancialPostingStatusReader;
use Modules\Core\Services\Delivery\NullDeliveryFeatureService;
use Modules\Core\Http\Middleware\ResolveTenantMiddleware;
use Modules\Core\Support\ModuleFeature;
use Modules\Installer\Http\Middleware\CheckInstalled;
use Modules\Setting\Models\Setting;
use Modules\Setting\Repositories\SettingRepository;
use Spatie\Permission\Middleware\PermissionMiddleware;
use Spatie\Permission\Middleware\RoleMiddleware;
use Spatie\Permission\Middleware\RoleOrPermissionMiddleware;

class CoreServiceProvider extends ServiceProvider
{
    /**
     * Core module specific middlewares.
     */
    protected array $middlewares = [
        'role' => RoleMiddleware::class,
        'permission' => PermissionMiddleware::class,
        'role_or_permission' => RoleOrPermissionMiddleware::class,
        'checkInstalled' => CheckInstalled::class,
        'tenant' => ResolveTenantMiddleware::class,
    ];

    /**
     * Boot the application events.
     */
    public function boot(): void
    {
        $this->setupAppCacheDriver();
        $this->registerFeatureBindings();
        $this->registerSetting();
        $this->setupAppLocale();
        $this->setupAppName();
        $this->setupMailConfig();
        $this->setupFilesystem();
        $this->setupAppTimezone();
        $this->activityLog();
        $this->registerMiddleware();
        $this->registerCommands();
    }

    /**
     * Setup application cache driver.
     */
    private function setupAppCacheDriver(): void
    {
        $this->app['config']->set('cache.default', Forkiva::installed() && Forkiva::cacheEnabled() ? config('cache.default') : 'array');
    }

    /**
     * Register feature bindings that may be fulfilled by optional modules.
     */
    private function registerFeatureBindings(): void
    {
        $this->registerOptionalModuleBindings('Delivery', [
            DeliveryFeatureInterface::class => [
                'service' => 'Modules\\Delivery\\Services\\Delivery\\DeliveryService',
                'fallback' => NullDeliveryFeatureService::class,
            ],
        ]);

        $this->registerOptionalModuleBindings('Accounting', [
            FinancialExpenseSummaryReaderInterface::class => [
                'service' => 'Modules\\Accounting\\Services\\FinancialExpenseSummaryReader\\FinancialExpenseSummaryReader',
                'fallback' => NullFinancialExpenseSummaryReader::class,
            ],
            FinancialPostingBridgeInterface::class => [
                'service' => 'Modules\\Accounting\\Services\\AccountingFinancialPostingBridge\\AccountingFinancialPostingBridge',
                'fallback' => NullFinancialPostingBridge::class,
            ],
            FinancialAccountLookupInterface::class => [
                'service' => 'Modules\\Accounting\\Services\\FinancialAccountLookup\\FinancialAccountLookup',
                'fallback' => NullFinancialAccountLookup::class,
            ],
            FinancialPostingStatusReaderInterface::class => [
                'service' => 'Modules\\Accounting\\Services\\FinancialPostingStatusReader\\FinancialPostingStatusReader',
                'fallback' => NullFinancialPostingStatusReader::class,
            ],
            FinancialInventoryBalanceReaderInterface::class => [
                'service' => 'Modules\\Accounting\\Services\\FinancialInventoryBalanceReader\\FinancialInventoryBalanceReader',
                'fallback' => NullFinancialInventoryBalanceReader::class,
            ],
        ]);
    }

    /**
     * Register singleton services supplied by an optional module.
     *
     * @param  array<class-string, array{service: class-string, fallback: class-string}>  $bindings
     */
    private function registerOptionalModuleBindings(string $module, array $bindings): void
    {
        foreach ($bindings as $contract => $binding) {
            $this->app->singleton(
                $contract,
                fn ($app) => $app->make(
                    ModuleFeature::enabled($module) && class_exists($binding['service'])
                        ? $binding['service']
                        : $binding['fallback']
                )
            );
        }
    }

    /**
     * Register setting binding.
     */
    private function registerSetting(): void
    {
        $this->app->singleton('setting', fn () => new SettingRepository(Setting::allCached()));
    }

    /**
     * Setup application name.
     */
    private function setupAppName(): void
    {
        $this->app['config']->set('app.name', setting('app_name', config('app.name')));
    }

    /**
     * Setup application mail config.
     */
    private function setupMailConfig(): void
    {
        if (! setting('services.notifications.email.enabled', false)) {
            return;
        }

        $this->app['config']->set(
            'mail.default',
            setting('services.notifications.email.mailer', config('mail.default', 'smtp'))
        );
        $this->app['config']->set(
            'mail.from.address',
            setting('services.notifications.email.from_address', config('mail.from.address'))
        );
        $this->app['config']->set(
            'mail.from.name',
            setting('services.notifications.email.from_name', setting('app_name', config('mail.from.name')))
        );

        if ($this->app['config']->get('mail.default') === 'smtp') {
            $this->app['config']->set(
                'mail.mailers.smtp.host',
                setting('services.notifications.email.smtp.host', config('mail.mailers.smtp.host'))
            );
            $this->app['config']->set(
                'mail.mailers.smtp.port',
                setting('services.notifications.email.smtp.port', config('mail.mailers.smtp.port'))
            );
            $this->app['config']->set(
                'mail.mailers.smtp.username',
                setting('services.notifications.email.smtp.username', config('mail.mailers.smtp.username'))
            );
            $this->app['config']->set(
                'mail.mailers.smtp.password',
                setting('services.notifications.email.smtp.password', config('mail.mailers.smtp.password'))
            );
            $this->app['config']->set(
                'mail.mailers.smtp.encryption',
                setting('services.notifications.email.smtp.encryption', config('mail.mailers.smtp.encryption'))
            );
        }
    }

    /**
     * Setup application filesystem config.
     */
    private function setupFilesystem(): void
    {
        $disk = setting('default_filesystem_disk', config('filesystems.default'));
        $privateDisk = setting('default_private_filesystem_disk', config('filesystems.default_private'));

        $this->app['config']->set('filesystems.default', $disk === 's3' ? 's3' : $disk);
        $this->app['config']->set('filesystems.default_private', $privateDisk === 's3' ? 's3_private' : $privateDisk);
        $this->app['config']->set('media-library.disk_name', $disk);

        if ($disk === 's3') {
            $this->app['config']->set('filesystems.disks.s3.key', setting('filesystem_s3_public_key', setting('filesystem_s3_key')));
            $this->app['config']->set('filesystems.disks.s3.secret', setting('filesystem_s3_public_secret', setting('filesystem_s3_secret')));
            $this->app['config']->set('filesystems.disks.s3.region', setting('filesystem_s3_public_region', setting('filesystem_s3_region')));
            $this->app['config']->set('filesystems.disks.s3.bucket', setting('filesystem_s3_public_bucket', setting('filesystem_s3_bucket')));
            $this->app['config']->set('filesystems.disks.s3.url', setting('filesystem_s3_public_url', setting('filesystem_s3_url')));
            $this->app['config']->set('filesystems.disks.s3.endpoint', setting('filesystem_s3_public_endpoint', setting('filesystem_s3_endpoint')));
            $this->app['config']->set('filesystems.disks.s3.use_path_style_endpoint', (bool) setting('filesystem_s3_public_use_path_style_endpoint', setting('filesystem_s3_use_path_style_endpoint', false)));
        }

        if ($privateDisk === 's3') {
            $this->app['config']->set('filesystems.disks.s3_private.key', setting('filesystem_s3_private_key', setting('filesystem_s3_key')));
            $this->app['config']->set('filesystems.disks.s3_private.secret', setting('filesystem_s3_private_secret', setting('filesystem_s3_secret')));
            $this->app['config']->set('filesystems.disks.s3_private.region', setting('filesystem_s3_private_region', setting('filesystem_s3_region')));
            $this->app['config']->set('filesystems.disks.s3_private.bucket', setting('filesystem_s3_private_bucket', setting('filesystem_s3_bucket')));
            $this->app['config']->set('filesystems.disks.s3_private.url', setting('filesystem_s3_private_url', setting('filesystem_s3_url')));
            $this->app['config']->set('filesystems.disks.s3_private.endpoint', setting('filesystem_s3_private_endpoint', setting('filesystem_s3_endpoint')));
            $this->app['config']->set('filesystems.disks.s3_private.use_path_style_endpoint', (bool) setting('filesystem_s3_private_use_path_style_endpoint', setting('filesystem_s3_use_path_style_endpoint', false)));
        }
    }

    /**
     * Setup application locale.
     */
    private function setupAppLocale(): void
    {
        $this->app['config']->set('app.locale', $defaultLocale = setting('default_locale', $this->app['config']->get('app.locale')));
        $this->app->setLocale($defaultLocale);
    }

    /**
     * Setup application timezone.
     */
    private function setupAppTimezone(): void
    {
        $timezone = setting('default_timezone') ?: config('app.timezone');

        date_default_timezone_set($timezone);

        $this->app['config']->set('app.timezone', $timezone);
    }

    /**
     * Enabled activity log.
     */
    private function activityLog(): void
    {
        $this->app['config']->set(
            'activitylog.enabled',
            $this->app->runningInConsole()
                ? false
                : setting(
                    'activity_log.enabled',
                    $this->app['config']->get('activity_log.enabled')
                )
        );

        $this->app['config']->set(
            'activitylog.delete_records_older_than_days',
            setting(
                'activity_log.delete_records_older_than_days',
                $this->app['config']->get('activity_log.delete_records_older_than_days')
            )
        );
    }

    /**
     * Register the filters.
     */
    private function registerMiddleware(): void
    {
        foreach ($this->middlewares as $name => $middleware) {
            $this->app['router']->aliasMiddleware($name, $middleware);
        }
    }

    /**
     * Register command
     */
    private function registerCommands(): void
    {
        $this->commands([UpgradeCommand::class]);
    }
}
