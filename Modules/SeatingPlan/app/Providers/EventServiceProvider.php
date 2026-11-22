<?php

namespace Modules\SeatingPlan\Providers;

use Illuminate\Foundation\Support\Providers\EventServiceProvider as ServiceProvider;
use Modules\SeatingPlan\Events\TableAssignWaiter;
use Modules\SeatingPlan\Events\TableUpdateStatus;
use Modules\SeatingPlan\Listeners\SendTableAssignedToWaiterNotification;
use Modules\SeatingPlan\Listeners\SendTableStatusChangedNotification;
use Modules\SeatingPlan\Listeners\StoreTableStatusLogo;

class EventServiceProvider extends ServiceProvider
{
    /**
     * The event listener mappings for the application.
     *
     * @var array
     */
    protected $listen = [
        TableUpdateStatus::class => [
            StoreTableStatusLogo::class,
            SendTableStatusChangedNotification::class,
        ],
        TableAssignWaiter::class => [
            SendTableAssignedToWaiterNotification::class,
        ],
    ];
}
