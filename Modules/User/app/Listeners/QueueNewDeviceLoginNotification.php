<?php

namespace Modules\User\Listeners;

use Illuminate\Auth\Events\Login;
use Modules\User\Jobs\SendNewDeviceLoginNotificationJob;

class QueueNewDeviceLoginNotification
{
    /**
     * Handle the event.
     */
    public function handle(Login $event): void
    {
        SendNewDeviceLoginNotificationJob::dispatch(
            $event->user->getAuthIdentifier(),
            request()->ip(),
            request()->userAgent(),
            app()->getLocale(),
            now()
        )->delay(now()->addSeconds(5));
    }
}
