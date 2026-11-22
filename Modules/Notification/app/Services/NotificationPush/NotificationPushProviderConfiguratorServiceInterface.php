<?php

namespace Modules\Notification\Services\NotificationPush;

use Modules\Service\Enums\PushNotificationProvider;

interface NotificationPushProviderConfiguratorServiceInterface
{
    /**
     * Configure and return the active push provider from Service settings.
     */
    public function activeProvider(): ?PushNotificationProvider;
}
