<?php

namespace Modules\Reservation\Providers;

use Illuminate\Foundation\Support\Providers\EventServiceProvider as ServiceProvider;
use Modules\Reservation\Events\ReservationCreated;
use Modules\Reservation\Events\ReservationStatusChanged;
use Modules\Reservation\Events\ReservationTablesAssigned;
use Modules\Reservation\Events\ReservationUpdated;
use Modules\Reservation\Events\WaitlistConvertedToReservation;
use Modules\Reservation\Listeners\SendReservationCreatedNotification;
use Modules\Reservation\Listeners\SendReservationStatusChangedNotification;
use Modules\Reservation\Listeners\SendReservationTablesAssignedNotification;
use Modules\Reservation\Listeners\SendReservationUpdatedNotification;
use Modules\Reservation\Listeners\SendWaitlistConvertedNotification;

class EventServiceProvider extends ServiceProvider
{
    /**
     * The event listener mappings for the application.
     *
     * @var array
     */
    protected $listen = [
        ReservationCreated::class => [
            SendReservationCreatedNotification::class,
        ],
        ReservationUpdated::class => [
            SendReservationUpdatedNotification::class,
        ],
        ReservationStatusChanged::class => [
            SendReservationStatusChangedNotification::class,
        ],
        ReservationTablesAssigned::class => [
            SendReservationTablesAssignedNotification::class,
        ],
        WaitlistConvertedToReservation::class => [
            SendWaitlistConvertedNotification::class,
        ],
    ];
}
