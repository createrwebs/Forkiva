<?php

namespace Modules\Reservation\Events;

use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Queue\SerializesModels;
use Modules\Reservation\Enums\ReservationStatus;
use Modules\Reservation\Models\Reservation;

class ReservationStatusChanged implements ShouldDispatchAfterCommit
{
    use SerializesModels;

    /**
     * Create a new event instance.
     */
    public function __construct(
        public Reservation $reservation,
        public ?ReservationStatus $oldStatus,
        public ReservationStatus $newStatus,
        public ?int $changedById = null,
        public ?string $reason = null,
    ) {}
}
