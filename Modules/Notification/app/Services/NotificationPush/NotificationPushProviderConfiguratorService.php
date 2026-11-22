<?php

namespace Modules\Notification\Services\NotificationPush;

use Kreait\Firebase\Contract\Messaging;
use Modules\Service\Enums\PushNotificationProvider;

class NotificationPushProviderConfiguratorService implements NotificationPushProviderConfiguratorServiceInterface
{
    /** {@inheritDoc} */
    public function activeProvider(): ?PushNotificationProvider
    {
        if (! filter_var(setting('services.notifications.push_notification.enabled', false), FILTER_VALIDATE_BOOLEAN)) {
            return null;
        }

        $provider = PushNotificationProvider::tryFrom(
            (string) setting('services.notifications.push_notification.provider', '')
        );

        if (! $provider) {
            return null;
        }

        match ($provider) {
            PushNotificationProvider::Fcm => $this->configureFcm(),
        };

        return $provider;
    }

    /**
     * Configure Firebase from Service settings.
     */
    protected function configureFcm(): void
    {
        $credentials = setting('services.notifications.push_notification.fcm.credentials');

        if ($credentials) {
            config(['firebase.projects.app.credentials' => $credentials]);
        }

        app()->forgetInstance(Messaging::class);
        app()->forgetInstance('firebase.messaging');
    }
}
