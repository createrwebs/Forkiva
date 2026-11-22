<?php

namespace Modules\Sms\Providers;

use Illuminate\Contracts\Support\DeferrableProvider;
use Illuminate\Support\ServiceProvider;
use Modules\Sms\Services\SmsMessage\SmsMessageService;
use Modules\Sms\Services\SmsMessage\SmsMessageServiceInterface;
use Modules\Sms\Services\SmsProvider\SmsProviderConfiguratorService;
use Modules\Sms\Services\SmsProvider\SmsProviderConfiguratorServiceInterface;

class DeferredSmsServiceProvider extends ServiceProvider implements DeferrableProvider
{
    /**
     * Register SMS services.
     */
    public function register(): void
    {
        $this->app->singleton(
            SmsProviderConfiguratorServiceInterface::class,
            fn ($app) => $app->make(SmsProviderConfiguratorService::class)
        );

        $this->app->singleton(
            SmsMessageServiceInterface::class,
            fn ($app) => $app->make(SmsMessageService::class)
        );
    }

    /**
     * Get the services provided by the provider.
     */
    public function provides(): array
    {
        return [
            SmsProviderConfiguratorServiceInterface::class,
            SmsMessageServiceInterface::class,
        ];
    }
}
