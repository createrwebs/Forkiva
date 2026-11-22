<?php

namespace Modules\Reservation\Services\ReservationWaitlist;

use App\Forkiva;
use DB;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Modules\Branch\Models\Branch;
use Modules\Branch\Services\BranchAccess\BranchAccessServiceInterface;
use Modules\Reservation\Enums\ReservationWaitlistStatus;
use Modules\Reservation\Events\WaitlistConvertedToReservation;
use Modules\Reservation\Jobs\SendWaitlistNotificationJob;
use Modules\Reservation\Models\Reservation;
use Modules\Reservation\Models\ReservationWaitlist;
use Modules\Reservation\Services\Reservation\ReservationServiceInterface;
use Modules\Reservation\Services\ReservationSetting\ReservationSettingServiceInterface;
use Modules\SeatingPlan\Models\Table;
use Modules\Support\Country;
use Modules\Support\GlobalStructureFilters;
use Modules\User\Enums\DefaultRole;
use Modules\User\Models\User;

class ReservationWaitlistService implements ReservationWaitlistServiceInterface
{
    /**
     * Create the waitlist service with reservation, notification, and settings dependencies.
     */
    public function __construct(
        protected ReservationServiceInterface $reservations,
        protected ReservationSettingServiceInterface $settings,
        protected BranchAccessServiceInterface $branchAccess,
    ) {}

    /** {@inheritDoc} */
    public function label(): string
    {
        return __('reservation::reservation_waitlists.reservation_waitlist');
    }

    /** {@inheritDoc} */
    public function exportResourceKey(): string
    {
        return 'reservation_waitlists';
    }

    /** {@inheritDoc} */
    public function exportPermission(): string
    {
        return 'admin.reservation_waitlists.export';
    }

    /** {@inheritDoc} */
    public function exportModelClass(): string
    {
        return ReservationWaitlist::class;
    }

    /** {@inheritDoc} */
    public function destroy(int|string|array $ids): bool
    {
        return DB::transaction(function () use ($ids): bool {
            $waitlists = ReservationWaitlist::query()
                ->whereIn('id', parseIds($ids))
                ->lockForUpdate()
                ->get();
            abort_if(
                $waitlists->contains(fn (ReservationWaitlist $waitlist) => $waitlist->status === ReservationWaitlistStatus::Converted),
                422,
                __('reservation::messages.converted_waitlist_cannot_delete'),
            );

            return ReservationWaitlist::query()->whereKey($waitlists->modelKeys())->delete() ?: false;
        });
    }

    /** {@inheritDoc} */
    public function get(array $filters = [], ?array $sorts = []): LengthAwarePaginator
    {
        return $this->exportQuery($filters, $sorts)
            ->paginate(Forkiva::paginate())
            ->withQueryString();
    }

    /** {@inheritDoc} */
    public function exportQuery(array $filters = [], ?array $sorts = [], array $context = []): Builder
    {
        return ReservationWaitlist::query()
            ->with(['branch:id,name', 'createdBy:id,name', 'convertedReservation:id,reservation_number'])
            ->filters($filters)
            ->sortBy($sorts ?: [
                ['key' => 'preferred_date', 'order' => 'desc'],
                ['key' => 'priority', 'order' => 'desc'],
            ]);
    }

    /** {@inheritDoc} */
    public function notify(int $id): ReservationWaitlist
    {
        return DB::transaction(function () use ($id): ReservationWaitlist {
            $waitlist = ReservationWaitlist::query()->lockForUpdate()->findOrFail($id);
            $this->assertActionable($waitlist, [ReservationWaitlistStatus::Waiting]);
            $notifiedAt = now();
            $waitlist->update([
                'status' => ReservationWaitlistStatus::Notified,
                'notified_at' => $notifiedAt,
            ]);

            SendWaitlistNotificationJob::dispatch(
                $waitlist->id,
                $notifiedAt->toDateTimeString(),
            )->afterCommit();

            return $waitlist->fresh($this->showRelations());
        });
    }

    /**
     * Assert the waitlist is in a state that permits the requested operation.
     *
     * @param  array<int, ReservationWaitlistStatus>  $allowed
     */
    protected function assertActionable(ReservationWaitlist $waitlist, array $allowed): void
    {
        abort_unless(
            in_array($waitlist->status, $allowed, true),
            422,
            __('reservation::messages.waitlist_action_invalid_status'),
        );
    }

    /** {@inheritDoc} */
    public function update(int $id, array $data): ReservationWaitlist
    {
        return DB::transaction(function () use ($id, $data): ReservationWaitlist {
            $waitlist = ReservationWaitlist::query()->lockForUpdate()->findOrFail($id);
            $this->assertActionable($waitlist, [
                ReservationWaitlistStatus::Waiting,
                ReservationWaitlistStatus::Notified,
            ]);
            $waitlist->update($data);

            return $waitlist->load($this->showRelations());
        });
    }

    /**
     * Get relations required by the waitlist detail response.
     *
     * @return array<int, string>
     */
    protected function showRelations(): array
    {
        return [
            'branch:id,name',
            'createdBy:id,name',
            'customer:id,name,phone,phone_country_iso_code',
            'convertedReservation:id,reservation_number',
        ];
    }

    /** {@inheritDoc} */
    public function show(int $id): ReservationWaitlist
    {
        return ReservationWaitlist::query()
            ->with($this->showRelations())
            ->findOrFail($id);
    }

    /** {@inheritDoc} */
    public function convert(int $id, array $data): Reservation
    {
        return DB::transaction(function () use ($id, $data) {
            $waitlist = ReservationWaitlist::query()
                ->with($this->showRelations())
                ->lockForUpdate()
                ->findOrFail($id);
            $this->assertActionable($waitlist, [
                ReservationWaitlistStatus::Waiting,
                ReservationWaitlistStatus::Notified,
            ]);
            $reservation = $this->reservations->store([
                'branch_id' => $waitlist->branch_id,
                'customer_id' => $waitlist->customer_id,
                'phone_country_iso_code' => $waitlist->phone_country_iso_code,
                'customer_name' => $waitlist->customer_name,
                'customer_phone' => $waitlist->customer_phone,
                'customer_email' => $waitlist->customer_email,
                'reservation_date' => $data['reservation_date'] ?? $waitlist->preferred_date->toDateString(),
                'start_time' => $data['start_time'] ?? $waitlist->preferred_start_time,
                'duration_minutes' => $data['duration_minutes'] ?? null,
                'guest_count' => $waitlist->guest_count,
                'occasion' => $waitlist->occasion,
                'special_requests' => $waitlist->special_requests,
                'internal_notes' => $waitlist->internal_notes,
                'table_ids' => $data['table_ids'] ?? [],
            ]);

            $waitlist->update([
                'status' => ReservationWaitlistStatus::Converted,
                'converted_reservation_id' => $reservation->id,
                'converted_at' => now(),
            ]);

            event(new WaitlistConvertedToReservation($waitlist->fresh(['convertedReservation', 'branch'])));

            return $reservation;
        });
    }

    /** {@inheritDoc} */
    public function store(array $data): ReservationWaitlist
    {
        $setting = $this->settings->forBranch((int) $data['branch_id']);
        abort_if(! $setting->waitlist_enabled, 422, __('reservation::messages.waitlist_not_enabled'));

        return ReservationWaitlist::query()
            ->create([
                'branch_id' => $data['branch_id'],
                'customer_id' => $data['customer_id'] ?? null,
                'phone_country_iso_code' => $data['phone_country_iso_code'],
                'customer_name' => $data['customer_name'],
                'customer_phone' => $data['customer_phone'],
                'customer_email' => $data['customer_email'] ?? null,
                'preferred_date' => $data['preferred_date'],
                'preferred_start_time' => $data['preferred_start_time'] ?? null,
                'preferred_end_time' => $data['preferred_end_time'] ?? null,
                'guest_count' => $data['guest_count'],
                'estimated_wait_minutes' => $data['estimated_wait_minutes'] ?? null,
                'priority' => $data['priority'] ?? 0,
                'status' => $data['status'] ?? ReservationWaitlistStatus::Waiting,
                'occasion' => $data['occasion'] ?? null,
                'special_requests' => $data['special_requests'] ?? null,
                'internal_notes' => $data['internal_notes'] ?? null,
                'expires_at' => $data['expires_at'] ?? $this->defaultExpiresAt($setting->waitlist_expiry_minutes),
            ])
            ->load($this->showRelations());
    }

    /**
     * Resolve default waitlist expiry timestamp from branch settings.
     */
    protected function defaultExpiresAt(?int $expiryMinutes): ?Carbon
    {
        return $expiryMinutes ? now()->addMinutes($expiryMinutes) : null;
    }

    /** {@inheritDoc} */
    public function cancel(int $id, array $data = []): ReservationWaitlist
    {
        return DB::transaction(function () use ($id, $data): ReservationWaitlist {
            $waitlist = ReservationWaitlist::query()->lockForUpdate()->findOrFail($id);
            $this->assertActionable($waitlist, [
                ReservationWaitlistStatus::Waiting,
                ReservationWaitlistStatus::Notified,
            ]);
            $waitlist->update([
                'status' => ReservationWaitlistStatus::Cancelled,
                'cancelled_at' => now(),
                'internal_notes' => $data['reason'] ?? $waitlist->internal_notes,
            ]);

            return $waitlist->fresh($this->showRelations());
        });
    }

    /** {@inheritDoc} */
    public function getStructureFilters(): array
    {
        $branchFilter = GlobalStructureFilters::branch();

        return [
            ...(is_null($branchFilter) ? [] : [$branchFilter]),
            [
                'key' => 'status',
                'label' => __('reservation::reservation_waitlists.filters.status'),
                'type' => 'select',
                'options' => ReservationWaitlistStatus::toArrayTrans(),
            ],
            GlobalStructureFilters::from(),
            GlobalStructureFilters::to(),
        ];
    }

    /** {@inheritDoc} */
    public function getFormMeta(?int $branchId = null): array
    {
        $branchId = $this->branchAccess->resolve($branchId);

        return [
            'branches' => Branch::list(true),
            'countries' => Country::toList(),
            'customers' => is_null($branchId) ? [] : User::list($branchId, DefaultRole::Customer, withDetails: true),
            'tables' => is_null($branchId) ? [] : Table::list($branchId),
        ];
    }
}
