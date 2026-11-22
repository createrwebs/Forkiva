<?php

namespace Modules\Reservation\Services\ReservationNotification;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Modules\Notification\Services\NotificationDispatcher\NotificationDispatcherServiceInterface;
use Modules\Notification\Support\GuestNotifiable;
use Modules\Notification\Support\NotificationVariableValue;
use Modules\Notification\Support\NotificationWorkflowRecipientCatalog;
use Modules\Reservation\Enums\ReservationStatus;
use Modules\Reservation\Events\ReservationStatusChanged;
use Modules\Reservation\Models\Reservation;
use Modules\Reservation\Models\ReservationReminder;
use Modules\Reservation\Models\ReservationWaitlist;
use Modules\User\Enums\DefaultRole;
use Modules\User\Models\User;

class ReservationNotificationService implements ReservationNotificationServiceInterface
{
    /**
     * Create the notification service with the workflow dispatcher.
     */
    public function __construct(protected NotificationDispatcherServiceInterface $dispatcher) {}

    /** {@inheritDoc} */
    public function sendCreated(Reservation $reservation): void
    {
        $this->dispatch('reservation_created', $this->loadReservation($reservation), []);
    }

    /**
     * Dispatch reservation notification.
     *
     * @param  array<string, mixed>  $extraVariables
     */
    protected function dispatch(string $templateKey, Reservation $reservation, array $extraVariables, ?int $excludeUserId = null): void
    {
        $recipients = $this->recipients($reservation, $templateKey, $excludeUserId);
        if ($recipients === []) {
            return;
        }

        $this->dispatcher->send($templateKey, $recipients, [
            ...$this->variables($reservation),
            ...$extraVariables,
        ]);
    }

    /**
     * Resolve workflow recipients.
     *
     * @return array<int, User|GuestNotifiable>
     */
    protected function recipients(Reservation $reservation, string $templateKey, ?int $excludeUserId = null): array
    {
        return collect([
            $this->reservationGuestRecipient($reservation),
            $reservation->createdBy,
        ])
            ->merge($this->branchRecipients($reservation->branch_id, $templateKey))
            ->filter(function ($recipient) use ($excludeUserId) {
                if ($recipient instanceof User) {
                    return filled($recipient->id) && $recipient->id !== $excludeUserId;
                }

                return $recipient instanceof GuestNotifiable && $recipient->hasContactRoute();
            })
            ->unique(fn ($recipient) => $recipient instanceof User ? "user:{$recipient->id}" : "guest:{$recipient->email}:{$recipient->phone}")
            ->values()
            ->all();
    }

    /**
     * Build the guest recipient from reservation contact data.
     */
    protected function reservationGuestRecipient(Reservation $reservation): GuestNotifiable
    {
        return new GuestNotifiable(
            name: $reservation->customer_name,
            email: $reservation->customer_email,
            phone: $reservation->getRawOriginal('customer_phone') ?: $reservation->customer_phone,
            phoneCountryIsoCode: $reservation->phone_country_iso_code,
        );
    }

    /**
     * Resolve branch and admin recipients for a workflow.
     *
     * @return Collection<int, User>
     */
    protected function branchRecipients(int $branchId, string $templateKey): Collection
    {
        $roleKeys = $this->candidateRoleKeys($templateKey);
        $globalRoles = array_values(array_intersect($roleKeys, [DefaultRole::Admin->value]));
        $branchRoles = array_values(array_diff($roleKeys, $globalRoles, [DefaultRole::Customer->value]));

        $branchRecipients = $branchRoles === []
            ? collect()
            : User::query()
                ->withOutGlobalBranchPermission()
                ->where('branch_id', $branchId)
                ->whereHas('roles', fn (Builder $query) => $query->whereIn('name', $branchRoles))
                ->get();

        $globalRecipients = $globalRoles === []
            ? collect()
            : User::query()
                ->withOutGlobalBranchPermission()
                ->whereHas('roles', fn (Builder $query) => $query->whereIn('name', $globalRoles))
                ->get();

        return $branchRecipients->merge($globalRecipients)->unique('id')->values();
    }

    /**
     * Get candidate role keys for a workflow.
     *
     * @return array<int, string>
     */
    protected function candidateRoleKeys(string $templateKey): array
    {
        return collect(['email', 'in_app', 'push_notification', 'sms', 'whatsapp'])
            ->flatMap(fn (string $channel) => NotificationWorkflowRecipientCatalog::allowedRolesFor($templateKey, $channel))
            ->filter()
            ->unique()
            ->values()
            ->all();
    }

    /**
     * Build reservation notification variables.
     *
     * @return array<string, mixed>
     */
    protected function variables(Reservation $reservation): array
    {
        return [
            'type' => 'reservation',
            'type_id' => $reservation->id,
            'app_name' => NotificationVariableValue::appName(),
            'reservation_number' => $reservation->reservation_number,
            'branch_name' => $reservation->branch?->getTranslations('name') ?: '-',
            'customer_name' => $reservation->customer_name,
            'reservation_date' => $reservation->reservation_date?->toDateString(),
            'start_time' => $reservation->start_time,
            'guest_count' => $reservation->guest_count,
            'status' => $reservation->status->allTrans(),
            'cancellation_reason' => $reservation->cancellation_reason ?: '-',
            'table_name' => $reservation->tables->pluck('name')->join(', ') ?: '-',
        ];
    }

    /**
     * Load relationships needed for notification variables.
     */
    protected function loadReservation(Reservation $reservation): Reservation
    {
        return $reservation->loadMissing(['branch', 'customer', 'createdBy', 'tables']);
    }

    /** {@inheritDoc} */
    public function sendUpdated(Reservation $reservation): void
    {
        $this->dispatch('reservation_updated', $this->loadReservation($reservation), []);
    }

    /** {@inheritDoc} */
    public function sendStatusChanged(ReservationStatusChanged $event): void
    {
        $template = match ($event->newStatus) {
            ReservationStatus::Confirmed => 'reservation_confirmed',
            ReservationStatus::Cancelled => 'reservation_cancelled',
            ReservationStatus::Arrived => 'reservation_arrived',
            ReservationStatus::Seated => 'reservation_seated',
            ReservationStatus::Completed => 'reservation_completed',
            ReservationStatus::NoShow => 'reservation_no_show',
            ReservationStatus::Expired => 'reservation_expired',
            ReservationStatus::Rejected => 'reservation_rejected',
            default => 'reservation_updated',
        };

        $this->dispatch($template, $this->loadReservation($event->reservation), [
            'previous_status' => $event->oldStatus?->allTrans(),
            'new_status' => $event->newStatus->allTrans(),
            'reason' => $event->reason,
        ], $event->changedById);
    }

    /** {@inheritDoc} */
    public function sendTablesAssigned(Reservation $reservation): void
    {
        $this->dispatch('reservation_table_assigned', $this->loadReservation($reservation), []);
    }

    /** {@inheritDoc} */
    public function sendReminder(ReservationReminder $reminder): void
    {
        $this->dispatch('reservation_reminder', $this->loadReservation($reminder->reservation), []);
    }

    /** {@inheritDoc} */
    public function sendWaitlistNotified(ReservationWaitlist $waitlist): void
    {
        $this->dispatcher->send(
            'reservation_waitlist_notified',
            $this->waitlistRecipients($waitlist),
            [
                'type' => 'reservation_waitlist',
                'type_id' => $waitlist->id,
                'app_name' => NotificationVariableValue::appName(),
                'branch_name' => $waitlist->branch?->getTranslations('name') ?: '-',
                'customer_name' => $waitlist->customer_name,
                'preferred_date' => $waitlist->preferred_date?->toDateString(),
                'preferred_time' => $waitlist->preferred_start_time ?: '-',
                'guest_count' => $waitlist->guest_count,
            ]);
    }

    /**
     * Resolve waitlist recipients.
     *
     * @return array<int, User|GuestNotifiable>
     */
    protected function waitlistRecipients(ReservationWaitlist $waitlist): array
    {
        return $this->branchRecipients($waitlist->branch_id, 'reservation_waitlist_notified')
            ->push($this->waitlistGuestRecipient($waitlist))
            ->filter(fn ($recipient) => $recipient instanceof User || ($recipient instanceof GuestNotifiable && $recipient->hasContactRoute()))
            ->values()
            ->all();
    }

    /**
     * Build the guest recipient from waitlist contact data.
     */
    protected function waitlistGuestRecipient(ReservationWaitlist $waitlist): GuestNotifiable
    {
        return new GuestNotifiable(
            name: $waitlist->customer_name,
            email: $waitlist->customer_email,
            phone: $waitlist->customer_phone,
            phoneCountryIsoCode: $waitlist->phone_country_iso_code,
        );
    }

    /** {@inheritDoc} */
    public function sendWaitlistConverted(ReservationWaitlist $waitlist): void
    {
        $this->dispatcher->send(
            'reservation_waitlist_converted',
            $this->waitlistRecipients($waitlist),
            [
                'type' => 'reservation_waitlist',
                'type_id' => $waitlist->id,
                'app_name' => NotificationVariableValue::appName(),
                'branch_name' => $waitlist->branch?->getTranslations('name') ?: '-',
                'customer_name' => $waitlist->customer_name,
                'reservation_number' => $waitlist->convertedReservation?->reservation_number ?: '-',
            ]
        );
    }
}
