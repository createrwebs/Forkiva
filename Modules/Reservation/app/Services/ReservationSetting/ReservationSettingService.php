<?php

namespace Modules\Reservation\Services\ReservationSetting;

use App\Forkiva;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Modules\Branch\Models\Branch;
use Modules\Reservation\Models\ReservationSetting;
use Modules\Support\GlobalStructureFilters;

class ReservationSettingService implements ReservationSettingServiceInterface
{
    /** {@inheritDoc} */
    public function label(): string
    {
        return __('reservation::reservation_settings.reservation_setting');
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
        return ReservationSetting::query()
            ->with(['branch:id,name,timezone', 'createdBy:id,name'])
            ->filters($filters)
            ->sortBy($sorts ?: [['key' => 'updated_at', 'order' => 'desc']]);
    }

    /** {@inheritDoc} */
    public function exportResourceKey(): string
    {
        return 'reservation_settings';
    }

    /** {@inheritDoc} */
    public function exportPermission(): string
    {
        return 'admin.reservation_settings.export';
    }

    /** {@inheritDoc} */
    public function exportModelClass(): string
    {
        return ReservationSetting::class;
    }

    /** {@inheritDoc} */
    public function show(int $id): ReservationSetting
    {
        return ReservationSetting::query()
            ->with(['branch:id,name,timezone', 'createdBy:id,name'])
            ->findOrFail($id);
    }

    /** {@inheritDoc} */
    public function store(array $data): ReservationSetting
    {
        /** @var Branch $branch */
        $branch = Branch::withoutGlobalActive()->findOrFail((int) $data['branch_id']);

        return ReservationSetting::query()
            ->updateOrCreate(
                ['branch_id' => $branch->id],
                $this->payload($data, $branch)
            )
            ->load(['branch:id,name,timezone', 'createdBy:id,name']);
    }

    /**
     * Build persistence payload.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function payload(array $data, Branch $branch, ?ReservationSetting $setting = null): array
    {
        return [
            'branch_id' => $branch->id,
            'reservations_enabled' => (bool) ($data['reservations_enabled'] ?? $setting?->reservations_enabled ?? true),
            'auto_confirm_enabled' => (bool) ($data['auto_confirm_enabled'] ?? $setting?->auto_confirm_enabled ?? false),
            'default_duration_minutes' => (int) ($data['default_duration_minutes'] ?? $setting?->default_duration_minutes ?? 90),
            'min_duration_minutes' => (int) ($data['min_duration_minutes'] ?? $setting?->min_duration_minutes ?? 30),
            'max_duration_minutes' => (int) ($data['max_duration_minutes'] ?? $setting?->max_duration_minutes ?? 240),
            'buffer_minutes' => (int) ($data['buffer_minutes'] ?? $setting?->buffer_minutes ?? 15),
            'min_advance_minutes' => (int) ($data['min_advance_minutes'] ?? $setting?->min_advance_minutes ?? 60),
            'max_advance_days' => (int) ($data['max_advance_days'] ?? $setting?->max_advance_days ?? 30),
            'slot_interval_minutes' => (int) ($data['slot_interval_minutes'] ?? $setting?->slot_interval_minutes ?? 15),
            'min_guests' => (int) ($data['min_guests'] ?? $setting?->min_guests ?? 1),
            'max_guests' => (int) ($data['max_guests'] ?? $setting?->max_guests ?? 30),
            'large_party_threshold' => (int) ($data['large_party_threshold'] ?? $setting?->large_party_threshold ?? 8),
            'large_party_requires_approval' => (bool) ($data['large_party_requires_approval'] ?? $setting?->large_party_requires_approval ?? true),
            'allow_walk_in' => (bool) ($data['allow_walk_in'] ?? $setting?->allow_walk_in ?? true),
            'pending_blocks_capacity' => (bool) ($data['pending_blocks_capacity'] ?? $setting?->pending_blocks_capacity ?? true),
            'require_table_on_create' => (bool) ($data['require_table_on_create'] ?? $setting?->require_table_on_create ?? false),
            'require_table_on_confirm' => (bool) ($data['require_table_on_confirm'] ?? $setting?->require_table_on_confirm ?? false),
            'require_table_on_arrival' => (bool) ($data['require_table_on_arrival'] ?? $setting?->require_table_on_arrival ?? false),
            'require_table_on_seating' => (bool) ($data['require_table_on_seating'] ?? $setting?->require_table_on_seating ?? true),
            'cancellation_allowed' => (bool) ($data['cancellation_allowed'] ?? $setting?->cancellation_allowed ?? true),
            'require_cancellation_reason' => (bool) ($data['require_cancellation_reason'] ?? $setting?->require_cancellation_reason ?? true),
            'cancellation_deadline_minutes' => $data['cancellation_deadline_minutes'] ?? $setting?->cancellation_deadline_minutes,
            'grace_period_minutes' => (int) ($data['grace_period_minutes'] ?? $setting?->grace_period_minutes ?? 15),
            'auto_no_show_enabled' => (bool) ($data['auto_no_show_enabled'] ?? $setting?->auto_no_show_enabled ?? false),
            'pending_expiry_minutes' => (int) ($data['pending_expiry_minutes'] ?? $setting?->pending_expiry_minutes ?? 0),
            'deposit_enabled' => (bool) ($data['deposit_enabled'] ?? $setting?->deposit_enabled ?? false),
            'deposit_required_for_large_party' => (bool) ($data['deposit_required_for_large_party'] ?? $setting?->deposit_required_for_large_party ?? false),
            'deposit_type' => 'fixed',
            'deposit_amount' => (float) ($data['deposit_amount'] ?? $setting?->deposit_amount ?? 0),
            'deposit_refundable' => (bool) ($data['deposit_refundable'] ?? $setting?->deposit_refundable ?? true),
            'deposit_payment_deadline_minutes' => $data['deposit_payment_deadline_minutes'] ?? $setting?->deposit_payment_deadline_minutes,
            'reminder_enabled' => (bool) ($data['reminder_enabled'] ?? $setting?->reminder_enabled ?? true),
            'reminder_before_minutes' => (int) ($data['reminder_before_minutes'] ?? $setting?->reminder_before_minutes ?? 120),
            'waitlist_enabled' => (bool) ($data['waitlist_enabled'] ?? $setting?->waitlist_enabled ?? true),
            'waitlist_expiry_minutes' => $data['waitlist_expiry_minutes'] ?? $setting?->waitlist_expiry_minutes,
            'working_hours' => ReservationSetting::normalizeWorkingHours(
                $data['working_hours'] ?? $setting?->working_hours
            ),
        ];
    }

    /** {@inheritDoc} */
    public function update(int $id, array $data): ReservationSetting
    {
        $setting = ReservationSetting::query()->findOrFail($id);
        /** @var Branch $branch */
        $branch = Branch::withoutGlobalActive()->findOrFail($setting->branch_id);
        $setting->update($this->payload($data, $branch, $setting));

        return $setting->load(['branch:id,name,timezone', 'createdBy:id,name']);
    }

    /** {@inheritDoc} */
    public function destroy(int|string|array $ids): bool
    {
        return ReservationSetting::query()
            ->whereIn('id', parseIds($ids))
            ->delete() ?: false;
    }

    /** {@inheritDoc} */
    public function forBranch(int $branchId): ReservationSetting
    {
        /** @var Branch $branch */
        $branch = Branch::withoutGlobalActive()->findOrFail($branchId);

        return ReservationSetting::query()
            ->withOutGlobalBranchPermission()
            ->firstOrCreate(
                ['branch_id' => $branchId],
                $this->payload([], $branch)
            )
            ->load(['branch:id,name,timezone', 'createdBy:id,name']);
    }

    /** {@inheritDoc} */
    public function getStructureFilters(): array
    {
        $branchFilter = GlobalStructureFilters::branch();

        return [
            ...(is_null($branchFilter) ? [] : [$branchFilter]),
            [
                'key' => 'reservations_enabled',
                'label' => __('reservation::reservation_settings.filters.reservations_enabled'),
                'type' => 'boolean',
            ],
            GlobalStructureFilters::from(),
            GlobalStructureFilters::to(),
        ];
    }

    /** {@inheritDoc} */
    public function getFormMeta(): array
    {
        return [
            'branches' => Branch::list(),
            'working_hours' => ReservationSetting::defaultWorkingHours(),
        ];
    }
}
