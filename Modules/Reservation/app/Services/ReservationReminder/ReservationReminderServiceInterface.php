<?php

namespace Modules\Reservation\Services\ReservationReminder;

use Modules\Reservation\Models\Reservation;

interface ReservationReminderServiceInterface
{
    /**
     * Schedule reservation reminder when enabled.
     */
    public function scheduleFor(Reservation $reservation): void;

    /**
     * Send due reminders.
     */
    public function sendDue(): int;

    /**
     * Expire pending reservations after configured policy.
     */
    public function expirePending(): int;

    /**
     * Mark no-show reservations after grace period.
     */
    public function markNoShows(): int;

    /**
     * Expire waitlist entries after configured policy.
     */
    public function expireWaitlists(): int;
}
