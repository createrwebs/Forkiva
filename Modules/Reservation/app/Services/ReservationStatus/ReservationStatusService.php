<?php

namespace Modules\Reservation\Services\ReservationStatus;

use DB;
use Illuminate\Validation\ValidationException;
use Modules\Reservation\Enums\ReservationStatus;
use Modules\Reservation\Events\ReservationStatusChanged;
use Modules\Reservation\Models\Reservation;
use Modules\Reservation\Models\ReservationSetting;
use Modules\Reservation\Services\ReservationSetting\ReservationSettingServiceInterface;
use Modules\SeatingPlan\Enums\TableStatus;

class ReservationStatusService implements ReservationStatusServiceInterface
{
    /**
     * Create the status service with reservation settings access.
     */
    public function __construct(protected ReservationSettingServiceInterface $settings) {}

    /** {@inheritDoc} */
    public function transition(int|string|Reservation $reservation, ReservationStatus $status, array $data = []): Reservation
    {
        $reservationId = $reservation instanceof Reservation ? $reservation->id : $reservation;

        return DB::transaction(function () use ($reservationId, $status, $data) {
            $reservation = Reservation::query()
                ->with(['tables', 'branch', 'createdBy', 'customer'])
                ->lockForUpdate()
                ->findOrFail($reservationId);
            $setting = $this->settings->forBranch($reservation->branch_id);
            $oldStatus = $reservation->status;

            abort_if($oldStatus->isTerminal(), 422, __('reservation::messages.terminal_reservation_cannot_change'));
            abort_unless($oldStatus->canTransitionTo($status), 422, __('reservation::messages.reservation_invalid_status_transition'));
            $this->assertTransitionAllowed($reservation, $status, $data, $setting);

            $payload = [
                'status' => $status,
                'updated_by' => auth()->id(),
                ...$this->statusPayload($status, $data),
            ];

            $reservation->update($payload);
            $reservation->statusLogs()->create([
                'old_status' => $oldStatus,
                'new_status' => $status,
                'note' => $data['note'] ?? null,
                'reason' => $data['reason'] ?? null,
                'changed_by' => auth()->id(),
                'changed_at' => now(),
            ]);

            $this->syncTableState($reservation->fresh(['tables']), $status);

            $reservation = $reservation->fresh(['tables', 'statusLogs', 'branch', 'createdBy', 'customer']);
            event(new ReservationStatusChanged(
                reservation: $reservation,
                oldStatus: $oldStatus,
                newStatus: $status,
                changedById: auth()->id(),
                reason: $data['reason'] ?? null,
            ));

            return $reservation;
        });
    }

    /**
     * Assert target transition is allowed.
     *
     * @param  array<string, mixed>  $data
     */
    protected function assertTransitionAllowed(Reservation $reservation, ReservationStatus $status, array $data, ReservationSetting $setting): void
    {
        abort_if(
            $status === ReservationStatus::Seated && $setting->require_table_on_seating && ! $reservation->tables()->exists(),
            422,
            __('reservation::messages.table_required_before_seating')
        );

        abort_if(
            $status === ReservationStatus::Confirmed && $setting->require_table_on_confirm && ! $reservation->tables()->exists(),
            422,
            __('reservation::messages.table_required_before_confirming')
        );

        abort_if(
            $status === ReservationStatus::Arrived && $setting->require_table_on_arrival && ! $reservation->tables()->exists(),
            422,
            __('reservation::messages.table_required_before_arrival')
        );

        abort_if(
            $status === ReservationStatus::Cancelled && ! $setting->cancellation_allowed,
            422,
            __('reservation::messages.cancellation_not_allowed')
        );

        abort_if(
            $status === ReservationStatus::Cancelled && $this->cancellationDeadlinePassed($reservation, $setting),
            422,
            __('reservation::messages.cancellation_deadline_passed')
        );

        if ($status === ReservationStatus::Cancelled && $setting->require_cancellation_reason && blank($data['reason'] ?? null)) {
            throw ValidationException::withMessages([
                'reason' => [
                    __('validation.required', [
                        'attribute' => __('reservation::attributes.reservations.cancellation_reason'),
                    ]),
                ],
            ]);
        }
    }

    /**
     * Determine if the reservation is past the branch cancellation deadline.
     */
    protected function cancellationDeadlinePassed(Reservation $reservation, ReservationSetting $setting): bool
    {
        if (is_null($setting->cancellation_deadline_minutes) || ! $reservation->start_at) {
            return false;
        }

        return now()->greaterThan($reservation->start_at->copy()->subMinutes($setting->cancellation_deadline_minutes));
    }

    /**
     * Get update payload for status timestamps and actors.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function statusPayload(ReservationStatus $status, array $data): array
    {
        return match ($status) {
            ReservationStatus::Confirmed => ['confirmed_at' => now(), 'confirmed_by' => auth()->id()],
            ReservationStatus::Arrived => ['arrived_at' => now()],
            ReservationStatus::Seated => ['seated_at' => now(), 'seated_by' => auth()->id()],
            ReservationStatus::Completed => ['completed_at' => now(), 'completed_by' => auth()->id()],
            ReservationStatus::Cancelled => ['cancelled_at' => now(), 'cancelled_by' => auth()->id(), 'cancellation_reason' => $data['reason'] ?? null],
            ReservationStatus::Rejected => ['rejected_at' => now(), 'rejection_reason' => $data['reason'] ?? null],
            ReservationStatus::NoShow => ['no_show_at' => now(), 'no_show_reason' => $data['reason'] ?? null],
            ReservationStatus::Expired => ['expired_at' => now()],
            default => [],
        };
    }

    /**
     * Sync table status after reservation transition.
     */
    protected function syncTableState(Reservation $reservation, ReservationStatus $status): void
    {
        if (! $reservation->relationLoaded('tables')) {
            $reservation->load('tables');
        }

        $reservation->tables->loadExists('activeOrder');

        foreach ($reservation->tables as $table) {
            // A reservation must not release or reserve a table still serving an order.
            if ($table->active_order_exists) {
                continue;
            }

            match ($status) {
                ReservationStatus::Confirmed, ReservationStatus::Arrived => $table->updateStatusWithEvent(TableStatus::Reserved, auth()->id(), "RESERVATION:$reservation->reservation_number"),
                ReservationStatus::Seated => $table->updateStatusWithEvent(TableStatus::Occupied, auth()->id(), "RESERVATION_SEATED:$reservation->reservation_number"),
                ReservationStatus::Completed, ReservationStatus::Cancelled, ReservationStatus::Rejected, ReservationStatus::NoShow, ReservationStatus::Expired => $table->updateStatusWithEvent(TableStatus::Available, auth()->id(), "RESERVATION_RELEASED:$reservation->reservation_number"),
                default => null,
            };
        }
    }
}
