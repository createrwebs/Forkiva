<?php

namespace Modules\Reservation\Jobs;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Modules\Reservation\Enums\ReservationWaitlistStatus;
use Modules\Reservation\Models\ReservationWaitlist;
use Modules\Reservation\Services\ReservationNotification\ReservationNotificationServiceInterface;
use Throwable;

class SendWaitlistNotificationJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 5;

    /**
     * Create a queued waitlist notification attempt.
     */
    public function __construct(
        public int $waitlistId,
        public string $notifiedAt,
    ) {}

    /**
     * Send the notification when the waitlist entry still matches this request.
     */
    public function handle(ReservationNotificationServiceInterface $notifications): void
    {
        $waitlist = ReservationWaitlist::query()
            ->withOutGlobalBranchPermission()
            ->with(['branch', 'customer', 'createdBy'])
            ->find($this->waitlistId);

        if (
            ! $waitlist
            || $waitlist->status !== ReservationWaitlistStatus::Notified
            || $waitlist->notified_at?->toDateTimeString() !== $this->notifiedAt
        ) {
            return;
        }

        $notifications->sendWaitlistNotified($waitlist);
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

    /**
     * Restore a retryable waitlist state after terminal delivery failure.
     */
    public function failed(Throwable $exception): void
    {
        ReservationWaitlist::query()
            ->withOutGlobalBranchPermission()
            ->whereKey($this->waitlistId)
            ->where('status', ReservationWaitlistStatus::Notified->value)
            ->where('notified_at', $this->notifiedAt)
            ->update([
                'status' => ReservationWaitlistStatus::Waiting,
                'notified_at' => null,
            ]);
    }
}
