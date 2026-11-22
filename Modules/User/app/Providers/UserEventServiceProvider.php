<?php

namespace Modules\User\Providers;

use Illuminate\Auth\Events\Login;
use Illuminate\Foundation\Support\Providers\EventServiceProvider;
use Modules\User\Events\AccountActivated;
use Modules\User\Events\PasswordChanged;
use Modules\User\Events\ProfileUpdated;
use Modules\User\Listeners\QueueNewDeviceLoginNotification;
use Modules\User\Listeners\SendAccountActivatedNotification;
use Modules\User\Listeners\SendPasswordChangedNotification;
use Modules\User\Listeners\SendProfileUpdatedNotification;

class UserEventServiceProvider extends EventServiceProvider
{
    /**
     * The event listener mappings for the application.
     *
     * @var array<class-string, array<int, class-string>>
     */
    protected $listen = [
        AccountActivated::class => [
            SendAccountActivatedNotification::class,
        ],
        PasswordChanged::class => [
            SendPasswordChangedNotification::class,
        ],
        ProfileUpdated::class => [
            SendProfileUpdatedNotification::class,
        ],
        Login::class => [
            QueueNewDeviceLoginNotification::class,
        ],
    ];
}
