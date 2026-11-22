<?php

namespace Modules\Reservation\Listeners;

use Illuminate\Contracts\Queue\ShouldQueueAfterCommit;
use Illuminate\Foundation\Queue\Queueable;
use Modules\Reservation\Events\ReservationUpdated;
use Modules\Reservation\Services\ReservationNotification\ReservationNotificationServiceInterface;
use Modules\Reservation\Services\ReservationReminder\ReservationReminderServiceInterface;

class SendReservationUpdatedNotification implements ShouldQueueAfterCommit
{
    use Queueable;

    public int $tries = 5;

    /**
     * Handle the event.
     */
    public function handle(ReservationUpdated $event): void
    {
        app(ReservationReminderServiceInterface::class)->scheduleFor($event->reservation);
        app(ReservationNotificationServiceInterface::class)->sendUpdated($event->reservation);
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
