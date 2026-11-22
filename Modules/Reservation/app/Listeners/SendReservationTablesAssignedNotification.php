<?php

namespace Modules\Reservation\Listeners;

use Illuminate\Contracts\Queue\ShouldQueueAfterCommit;
use Illuminate\Foundation\Queue\Queueable;
use Modules\Reservation\Events\ReservationTablesAssigned;
use Modules\Reservation\Services\ReservationNotification\ReservationNotificationServiceInterface;

class SendReservationTablesAssignedNotification implements ShouldQueueAfterCommit
{
    use Queueable;

    public int $tries = 5;

    /**
     * Handle the event.
     */
    public function handle(ReservationTablesAssigned $event): void
    {
        app(ReservationNotificationServiceInterface::class)->sendTablesAssigned($event->reservation);
    }

    /**
     * Return retry delays in seconds.
     *
     * @return array<int, int>
     */
    public function backoff(): array
    {
        return [10, 30, 60, 180];
    }
}
