<?php

namespace Modules\User\Listeners;

use Modules\Notification\Services\NotificationDispatcher\NotificationDispatcherServiceInterface;
use Modules\Notification\Support\NotificationVariableValue;
use Modules\User\Events\PasswordChanged;

class SendPasswordChangedNotification
{
    /**
     * Handle the event.
     */
    public function handle(PasswordChanged $event): void
    {
        app()->setLocale($event->locale);

        app(NotificationDispatcherServiceInterface::class)
            ->send(
                templateKey: 'password_changed',
                notifiables: $event->user,
                variables: [
                    'user_name' => $event->user->name,
                    'changed_at' => dateTimeFormat($event->changedAt),
                    'app_name' => NotificationVariableValue::appName(),
                ],
                locale: $event->locale
            );
    }
}
