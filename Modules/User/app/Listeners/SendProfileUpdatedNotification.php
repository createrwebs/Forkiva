<?php

namespace Modules\User\Listeners;

use Modules\Notification\Services\NotificationDispatcher\NotificationDispatcherServiceInterface;
use Modules\Notification\Support\NotificationVariableValue;
use Modules\User\Events\ProfileUpdated;

class SendProfileUpdatedNotification
{
    /**
     * Handle the event.
     */
    public function handle(ProfileUpdated $event): void
    {
        app()->setLocale($event->locale);

        app(NotificationDispatcherServiceInterface::class)
            ->send(
                templateKey: 'profile_updated',
                notifiables: $event->user,
                variables: [
                    'user_name' => $event->user->name,
                    'updated_at' => dateTimeFormat($event->changedAt),
                    'app_name' => NotificationVariableValue::appName(),
                ],
                locale: $event->locale
            );
    }
}
