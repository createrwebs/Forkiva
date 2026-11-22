<?php

namespace Modules\Notification\Providers;

use Illuminate\Contracts\Support\DeferrableProvider;
use Illuminate\Support\ServiceProvider;
use Modules\Notification\Services\NotificationCenter\NotificationCenterService;
use Modules\Notification\Services\NotificationCenter\NotificationCenterServiceInterface;
use Modules\Notification\Services\NotificationChannel\NotificationChannelResolverService;
use Modules\Notification\Services\NotificationChannel\NotificationChannelResolverServiceInterface;
use Modules\Notification\Services\NotificationDispatcher\NotificationDispatcherService;
use Modules\Notification\Services\NotificationDispatcher\NotificationDispatcherServiceInterface;
use Modules\Notification\Services\NotificationPush\NotificationPushProviderConfiguratorService;
use Modules\Notification\Services\NotificationPush\NotificationPushProviderConfiguratorServiceInterface;
use Modules\Notification\Services\NotificationPushToken\NotificationPushTokenService;
use Modules\Notification\Services\NotificationPushToken\NotificationPushTokenServiceInterface;
use Modules\Notification\Services\NotificationRecipient\NotificationRecipientRoleResolverService;
use Modules\Notification\Services\NotificationRecipient\NotificationRecipientRoleResolverServiceInterface;
use Modules\Notification\Services\NotificationTemplate\NotificationTemplateRendererService;
use Modules\Notification\Services\NotificationTemplate\NotificationTemplateRendererServiceInterface;
use Modules\Notification\Services\NotificationTemplate\NotificationTemplateService;
use Modules\Notification\Services\NotificationTemplate\NotificationTemplateServiceInterface;
use Modules\Notification\Services\NotificationWorkflow\NotificationWorkflowService;
use Modules\Notification\Services\NotificationWorkflow\NotificationWorkflowServiceInterface;

class DeferredNotificationServiceProvider extends ServiceProvider implements DeferrableProvider
{
    /**
     * Register the service provider.
     */
    public function register(): void
    {
        $this->app->bind(
            abstract: NotificationChannelResolverServiceInterface::class,
            concrete: fn ($app) => $app->make(NotificationChannelResolverService::class)
        );

        $this->app->bind(
            abstract: NotificationTemplateServiceInterface::class,
            concrete: fn ($app) => $app->make(NotificationTemplateService::class)
        );

        $this->app->bind(
            abstract: NotificationTemplateRendererServiceInterface::class,
            concrete: fn ($app) => $app->make(NotificationTemplateRendererService::class)
        );

        $this->app->bind(
            abstract: NotificationCenterServiceInterface::class,
            concrete: fn ($app) => $app->make(NotificationCenterService::class)
        );

        $this->app->bind(
            abstract: NotificationWorkflowServiceInterface::class,
            concrete: fn ($app) => $app->make(NotificationWorkflowService::class)
        );

        $this->app->bind(
            abstract: NotificationRecipientRoleResolverServiceInterface::class,
            concrete: fn ($app) => $app->make(NotificationRecipientRoleResolverService::class)
        );

        $this->app->bind(
            abstract: NotificationPushProviderConfiguratorServiceInterface::class,
            concrete: fn ($app) => $app->make(NotificationPushProviderConfiguratorService::class)
        );

        $this->app->bind(
            abstract: NotificationPushTokenServiceInterface::class,
            concrete: fn ($app) => $app->make(NotificationPushTokenService::class)
        );

        $this->app->bind(
            abstract: NotificationDispatcherServiceInterface::class,
            concrete: fn ($app) => $app->make(NotificationDispatcherService::class)
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
            NotificationChannelResolverServiceInterface::class,
            NotificationTemplateServiceInterface::class,
            NotificationTemplateRendererServiceInterface::class,
            NotificationCenterServiceInterface::class,
            NotificationWorkflowServiceInterface::class,
            NotificationRecipientRoleResolverServiceInterface::class,
            NotificationPushProviderConfiguratorServiceInterface::class,
            NotificationPushTokenServiceInterface::class,
            NotificationDispatcherServiceInterface::class,
        ];
    }
}
