<?php

namespace Modules\User\Listeners;

use Modules\Notification\Services\NotificationDispatcher\NotificationDispatcherServiceInterface;
use Modules\Notification\Support\NotificationVariableValue;
use Modules\User\Events\AccountActivated;

class SendAccountActivatedNotification
{
    /**
     * Handle the event.
     */
    public function handle(AccountActivated $event): void
    {
        app()->setLocale($event->locale);

        $user = $event->user->loadMissing('branch');

        app(NotificationDispatcherServiceInterface::class)
            ->send(
                templateKey: 'account_activated',
                notifiables: $user,
                variables: [
                    'user_name' => $user->name,
                    'activation_date' => dateTimeFormat($event->activatedAt),
                    'app_name' => NotificationVariableValue::appName(),
                ],
                locale: $event->locale
            );
    }
}
