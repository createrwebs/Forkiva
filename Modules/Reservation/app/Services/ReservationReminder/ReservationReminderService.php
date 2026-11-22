<?php

namespace Modules\Reservation\Services\ReservationReminder;

use Modules\Reservation\Enums\ReservationReminderStatus;
use Modules\Reservation\Enums\ReservationStatus;
use Modules\Reservation\Enums\ReservationWaitlistStatus;
use Modules\Reservation\Events\ReservationReminderDue;
use Modules\Reservation\Models\Reservation;
use Modules\Reservation\Models\ReservationReminder;
use Modules\Reservation\Models\ReservationWaitlist;
use Modules\Reservation\Services\ReservationNotification\ReservationNotificationServiceInterface;
use Modules\Reservation\Services\ReservationSetting\ReservationSettingServiceInterface;
use Modules\Reservation\Services\ReservationStatus\ReservationStatusServiceInterface;
use Throwable;

class ReservationReminderService implements ReservationReminderServiceInterface
{
    /**
     * Create the reminder service with settings, notifications, and status transition dependencies.
     */
    public function __construct(
        protected ReservationSettingServiceInterface $settings,
        protected ReservationNotificationServiceInterface $notifications,
        protected ReservationStatusServiceInterface $statuses,
    ) {}

    /** {@inheritDoc} */
    public function scheduleFor(Reservation $reservation): void
    {
        $setting = $this->settings->forBranch($reservation->branch_id);
        if (! $setting->reminder_enabled || $reservation->status->isTerminal()) {
            $this->cancelPendingFor($reservation);

            return;
        }

        $scheduledAt = $reservation->start_at->copy()->subMinutes($setting->reminder_before_minutes);
        if ($scheduledAt->isPast()) {
            $this->cancelPendingFor($reservation);

            return;
        }

        ReservationReminder::query()
            ->updateOrCreate(
                [
                    'reservation_id' => $reservation->id,
                    'reminder_type' => 'before_reservation',
                    'channel' => 'workflow',
                ],
                [
                    'scheduled_at' => $scheduledAt,
                    'status' => ReservationReminderStatus::Pending,
                ]
            );
    }

    /**
     * Cancel pending reminders for a reservation.
     */
    protected function cancelPendingFor(Reservation $reservation): void
    {
        ReservationReminder::query()
            ->where('reservation_id', $reservation->id)
            ->where('status', ReservationReminderStatus::Pending->value)
            ->update(['status' => ReservationReminderStatus::Cancelled]);
    }

    /** {@inheritDoc} */
    public function sendDue(): int
    {
        $count = 0;
        ReservationReminder::query()
            ->where('status', ReservationReminderStatus::Pending->value)
            ->where('scheduled_at', '<=', now())
            ->with('reservation.branch')
            ->chunkById(50, function ($reminders) use (&$count) {
                foreach ($reminders as $reminder) {
                    try {
                        event(new ReservationReminderDue($reminder));
                        $this->notifications->sendReminder($reminder);
                        $reminder->update(['status' => ReservationReminderStatus::Sent, 'sent_at' => now()]);
                        $count++;
                    } catch (Throwable $exception) {
                        $reminder->update([
                            'status' => ReservationReminderStatus::Failed,
                            'failed_at' => now(),
                            'error_message' => $exception->getMessage(),
                        ]);
                    }
                }
            });

        return $count;
    }

    /** {@inheritDoc} */
    public function expirePending(): int
    {
        $count = 0;
        Reservation::query()
            ->where('status', ReservationStatus::Pending->value)
            ->chunkById(50, function ($reservations) use (&$count) {
                foreach ($reservations as $reservation) {
                    $setting = $this->settings->forBranch($reservation->branch_id);
                    $expiresAt = $setting->pending_expiry_minutes > 0 && $reservation->created_at
                        ? $reservation->created_at->copy()->addMinutes($setting->pending_expiry_minutes)
                        : $reservation->start_at;

                    if (! $expiresAt || $expiresAt->isFuture()) {
                        continue;
                    }

                    $this->statuses->transition($reservation, ReservationStatus::Expired);
                    $count++;
                }
            });

        return $count;
    }

    /** {@inheritDoc} */
    public function markNoShows(): int
    {
        $count = 0;
        Reservation::query()
            ->whereIn('status', [ReservationStatus::Confirmed->value, ReservationStatus::Arrived->value])
            ->where('start_at', '<', now())
            ->chunkById(50, function ($reservations) use (&$count) {
                foreach ($reservations as $reservation) {
                    $setting = $this->settings->forBranch($reservation->branch_id);
                    if (! $setting->auto_no_show_enabled || $reservation->start_at->copy()->addMinutes($setting->grace_period_minutes)->isFuture()) {
                        continue;
                    }

                    $this->statuses->transition($reservation, ReservationStatus::NoShow);
                    $count++;
                }
            });

        return $count;
    }

    /** {@inheritDoc} */
    public function expireWaitlists(): int
    {
        $count = 0;

        ReservationWaitlist::query()
            ->whereIn('status', [ReservationWaitlistStatus::Waiting->value, ReservationWaitlistStatus::Notified->value])
            ->chunkById(50, function ($waitlists) use (&$count) {
                foreach ($waitlists as $waitlist) {
                    $setting = $this->settings->forBranch($waitlist->branch_id);
                    $expiresAt = $waitlist->expires_at;

                    if (! $expiresAt && $setting->waitlist_expiry_minutes && $waitlist->created_at) {
                        $expiresAt = $waitlist->created_at->copy()->addMinutes($setting->waitlist_expiry_minutes);
                    }

                    if (! $expiresAt || $expiresAt->isFuture()) {
                        continue;
                    }

                    $waitlist->update([
                        'status' => ReservationWaitlistStatus::Expired,
                        'expired_at' => now(),
                    ]);
                    $count++;
                }
            });

        return $count;
    }
}
