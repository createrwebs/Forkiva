<?php

namespace Modules\Reservation\Services\ReservationNotification;

use Modules\Reservation\Events\ReservationStatusChanged;
use Modules\Reservation\Models\Reservation;
use Modules\Reservation\Models\ReservationReminder;
use Modules\Reservation\Models\ReservationWaitlist;

interface ReservationNotificationServiceInterface
{
    /**
     * Notify relevant channels when a reservation is created.
     */
    public function sendCreated(Reservation $reservation): void;

    /**
     * Notify relevant channels when a reservation is updated.
     */
    public function sendUpdated(Reservation $reservation): void;

    /**
     * Notify subscribers when reservation status changes.
     */
    public function sendStatusChanged(ReservationStatusChanged $event): void;

    /**
     * Notify users when tables are assigned to a reservation.
     */
    public function sendTablesAssigned(Reservation $reservation): void;

    /**
     * Send a reservation reminder notification.
     */
    public function sendReminder(ReservationReminder $reminder): void;

    /**
     * Notify when a waitlist entry has been sent a notification.
     */
    public function sendWaitlistNotified(ReservationWaitlist $waitlist): void;

    /**
     * Notify when a waitlist entry is converted to a reservation.
     */
    public function sendWaitlistConverted(ReservationWaitlist $waitlist): void;
}
