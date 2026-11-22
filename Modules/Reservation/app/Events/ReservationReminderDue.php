<?php

namespace Modules\Reservation\Events;

use Modules\Reservation\Models\ReservationReminder;

class ReservationReminderDue
{
    /**
     * Create a new event instance.
     */
    public function __construct(public ReservationReminder $reminder) {}
}
