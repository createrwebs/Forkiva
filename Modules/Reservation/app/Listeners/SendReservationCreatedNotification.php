<?php

namespace Modules\Reservation\Listeners;

use Illuminate\Contracts\Queue\ShouldQueueAfterCommit;
use Illuminate\Foundation\Queue\Queueable;
use Modules\Reservation\Events\ReservationCreated;
use Modules\Reservation\Services\ReservationNotification\ReservationNotificationServiceInterface;
use Modules\Reservation\Services\ReservationReminder\ReservationReminderServiceInterface;

class SendReservationCreatedNotification implements ShouldQueueAfterCommit
{
    use Queueable;

    public int $tries = 5;

    /**
     * Handle the event.
     */
    public function handle(ReservationCreated $event): void
    {
        app(ReservationReminderServiceInterface::class)->scheduleFor($event->reservation);
        app(ReservationNotificationServiceInterface::class)->sendCreated($event->reservation);
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
