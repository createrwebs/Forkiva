<?php

namespace Modules\Reservation\Listeners;

use Illuminate\Contracts\Queue\ShouldQueueAfterCommit;
use Illuminate\Foundation\Queue\Queueable;
use Modules\Reservation\Events\WaitlistConvertedToReservation;
use Modules\Reservation\Services\ReservationNotification\ReservationNotificationServiceInterface;

class SendWaitlistConvertedNotification implements ShouldQueueAfterCommit
{
    use Queueable;

    public int $tries = 5;

    /**
     * Handle the event.
     */
    public function handle(WaitlistConvertedToReservation $event): void
    {
        app(ReservationNotificationServiceInterface::class)->sendWaitlistConverted($event->waitlist);
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
