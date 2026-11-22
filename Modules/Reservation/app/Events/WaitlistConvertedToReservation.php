<?php

namespace Modules\Reservation\Events;

use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Queue\SerializesModels;
use Modules\Reservation\Models\ReservationWaitlist;

class WaitlistConvertedToReservation implements ShouldDispatchAfterCommit
{
    use SerializesModels;

    /**
     * Create a new event instance.
     */
    public function __construct(public ReservationWaitlist $waitlist) {}
}
