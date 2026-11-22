<?php

namespace Modules\Reservation\Events;

use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Queue\SerializesModels;
use Modules\Reservation\Models\Reservation;

class ReservationCreated implements ShouldDispatchAfterCommit
{
    use SerializesModels;

    /**
     * Create a new event instance.
     */
    public function __construct(public Reservation $reservation) {}
}
