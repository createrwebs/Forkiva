<?php

namespace Modules\Reservation\Services\Reservation;

use App\Forkiva;
use Carbon\Carbon;
use DB;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Modules\Branch\Models\Branch;
use Modules\Branch\Services\BranchAccess\BranchAccessServiceInterface;
use Modules\Reservation\Enums\ReservationPaymentStatus;
use Modules\Reservation\Enums\ReservationStatus;
use Modules\Reservation\Enums\ReservationType;
use Modules\Reservation\Enums\TableAssignmentMode;
use Modules\Reservation\Enums\TableAssignmentStatus;
use Modules\Reservation\Events\ReservationCreated;
use Modules\Reservation\Events\ReservationUpdated;
use Modules\Reservation\Models\Reservation;
use Modules\Reservation\Models\ReservationSetting;
use Modules\Reservation\Services\ReservationAvailability\ReservationAvailabilityServiceInterface;
use Modules\Reservation\Services\ReservationNumber\ReservationNumberServiceInterface;
use Modules\Reservation\Services\ReservationSetting\ReservationSettingServiceInterface;
use Modules\Reservation\Services\ReservationTableAssignment\ReservationTableAssignmentServiceInterface;
use Modules\Reservation\Support\ReservationDepositCurrencyRate;
use Modules\SeatingPlan\Models\Floor;
use Modules\SeatingPlan\Models\Table;
use Modules\SeatingPlan\Models\Zone;
use Modules\Support\Country;
use Modules\Support\GlobalStructureFilters;
use Modules\User\Enums\DefaultRole;
use Modules\User\Models\User;

class ReservationService implements ReservationServiceInterface
{
    /**
     * Create the reservation service with availability, numbering, settings, and assignment dependencies.
     */
    public function __construct(
        protected ReservationAvailabilityServiceInterface $availability,
        protected ReservationNumberServiceInterface $numbers,
        protected ReservationSettingServiceInterface $settings,
        protected ReservationTableAssignmentServiceInterface $assignment,
        protected BranchAccessServiceInterface $branchAccess,
    ) {}

    /** {@inheritDoc} */
    public function label(): string
    {
        return __('reservation::reservations.reservation');
    }

    /** {@inheritDoc} */
    public function getModel(): Reservation
    {
        return new ($this->model());
    }

    /** {@inheritDoc} */
    public function model(): string
    {
        return Reservation::class;
    }

    /** {@inheritDoc} */
    public function store(array $data): Reservation
    {
        return DB::transaction(function () use ($data) {
            $mode = $this->assignmentMode($data);
            $reservation = $this->createReservation($data);

            if ($mode === TableAssignmentMode::AssignNow) {
                $reservation = $this->assignment->assign($reservation, $data['table_ids']);
            } elseif ($mode === TableAssignmentMode::AutoAssign) {
                $reservation = $this->assignment->autoAssign($reservation);
            }

            $reservation = $reservation->fresh(['tables', 'reservationTables.table', 'reservationTables.assignedBy', 'branch', 'createdBy', 'customer']);
            event(new ReservationCreated($reservation));

            return $reservation;
        });
    }

    /**
     * Resolve assignment mode from request data and current reservation state.
     *
     * @param  array<string, mixed>  $data
     */
    protected function assignmentMode(array $data, ?Reservation $reservation = null): TableAssignmentMode
    {
        if (! empty($data['table_assignment_mode'])) {
            return $data['table_assignment_mode'] instanceof TableAssignmentMode
                ? $data['table_assignment_mode']
                : TableAssignmentMode::from($data['table_assignment_mode']);
        }

        if (! empty($data['table_ids'])) {
            return TableAssignmentMode::AssignNow;
        }

        return $reservation?->table_assignment_mode ?? TableAssignmentMode::AssignLater;
    }

    /**
     * Create a reservation record after validating availability.
     *
     * @param  array<string, mixed>  $data
     */
    protected function createReservation(array $data): Reservation
    {
        $mode = $this->assignmentMode($data);
        $setting = $this->settings->forBranch((int) $data['branch_id']);
        $duration = (int) ($data['duration_minutes'] ?? $setting->default_duration_minutes);
        $guestCount = (int) ($data['guest_count'] ?? 1);
        $initialStatus = $this->statusForPayload($data, null, $setting, $guestCount);
        $check = $this->availability->check([
            ...$data,
            'duration_minutes' => $duration,
            'table_assignment_mode' => $mode->value,
            'table_ids' => $mode === TableAssignmentMode::AssignNow ? ($data['table_ids'] ?? []) : [],
        ]);

        abort_unless($check['available'], 422, $check['warnings'][0] ?? __('reservation::messages.reservation_unavailable'));
        abort_if($setting->require_table_on_create && $mode === TableAssignmentMode::AssignLater, 422, __('reservation::messages.table_required_on_create'));
        $this->assertInitialStatusTablePolicy($initialStatus, $mode, $setting);

        return Reservation::query()
            ->create($this->payload([
                ...$data,
                'duration_minutes' => $duration,
                'start_at' => $check['start_at'],
                'end_at' => $check['end_at'],
                'end_time' => $this->endTimeFrom($check['end_at'], (int) $data['branch_id']),
            ]));
    }

    /**
     * Resolve initial status using branch auto-confirm settings.
     *
     * @param  array<string, mixed>  $data
     */
    protected function statusForPayload(
        array $data,
        ?Reservation $reservation,
        ReservationSetting $setting,
        int $guestCount
    ): string {
        if ($reservation?->status instanceof ReservationStatus) {
            return $reservation->status->value;
        }

        $requestedStatus = isset($data['status'])
            ? ($data['status'] instanceof ReservationStatus ? $data['status'] : ReservationStatus::from($data['status']))
            : null;

        if ($requestedStatus instanceof ReservationStatus && $requestedStatus !== ReservationStatus::Pending) {
            return $requestedStatus->value;
        }

        if ($setting->auto_confirm_enabled && ! $this->requiresLargePartyApproval($setting, $guestCount)) {
            return ReservationStatus::Confirmed->value;
        }

        return ($requestedStatus ?? ReservationStatus::Pending)->value;
    }

    /**
     * Determine if a reservation should remain pending for large-party approval.
     */
    protected function requiresLargePartyApproval(ReservationSetting $setting, int $guestCount): bool
    {
        return $setting->large_party_requires_approval && $guestCount >= $setting->large_party_threshold;
    }

    /**
     * Assert table requirements for the reservation initial status.
     */
    protected function assertInitialStatusTablePolicy(string $status, TableAssignmentMode $mode, ReservationSetting $setting): void
    {
        if ($mode !== TableAssignmentMode::AssignLater) {
            return;
        }

        abort_if(
            $status === ReservationStatus::Confirmed->value && $setting->require_table_on_confirm,
            422,
            __('reservation::messages.table_required_before_confirming')
        );

        abort_if(
            $status === ReservationStatus::Arrived->value && $setting->require_table_on_arrival,
            422,
            __('reservation::messages.table_required_before_arrival')
        );

        abort_if(
            $status === ReservationStatus::Seated->value && $setting->require_table_on_seating,
            422,
            __('reservation::messages.table_required_before_seating')
        );
    }

    /**
     * Build reservation payload.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function payload(array $data, ?Reservation $reservation = null): array
    {
        $branchId = (int) ($data['branch_id'] ?? $reservation?->branch_id);
        /** @var Branch $branch */
        $branch = Branch::withoutGlobalActive()->findOrFail($branchId);
        $setting = $this->settings->forBranch($branchId);
        $duration = (int) ($data['duration_minutes'] ?? $reservation?->duration_minutes ?? $setting->default_duration_minutes);

        if (! isset($data['start_at'], $data['end_at'])) {
            [$startAt, $endAt] = $this->availability->window(
                branchId: $branchId,
                date: $data['reservation_date'] ?? $reservation?->reservation_date->toDateString(),
                time: $data['start_time'] ?? $reservation?->start_time,
                durationMinutes: $duration
            );
            $data['start_at'] = $startAt->toDateTimeString();
            $data['end_at'] = $endAt->toDateTimeString();
            $data['end_time'] = $this->endTimeFrom($data['end_at'], $branchId);
        }

        $guestCount = (int) ($data['guest_count'] ?? $reservation?->guest_count ?? 1);
        $reservationType = $this->reservationTypeForPayload($data, $reservation);
        $this->assertSettingAllowsReservation($data, $reservation, $setting, $reservationType);
        $depositRequiredByPolicy = $setting->deposit_enabled
            && $setting->deposit_required_for_large_party
            && $guestCount >= $setting->large_party_threshold;
        $depositRequiredValue = $setting->deposit_enabled && (
            $depositRequiredByPolicy
            || (
                array_key_exists('deposit_required', $data)
                    ? filter_var($data['deposit_required'], FILTER_VALIDATE_BOOLEAN)
                    : ($reservation?->deposit_required ?? false)
            )
        );
        $depositAmount = match (true) {
            ! $depositRequiredValue => 0,
            $depositRequiredByPolicy => (float) $setting->deposit_amount,
            default => (float) ($data['deposit_amount'] ?? $reservation?->getRawOriginal('deposit_amount') ?? $setting->deposit_amount ?? 0),
        };
        $depositPaidAmount = (float) ($reservation?->getRawOriginal('deposit_paid_amount') ?? 0);
        abort_if(
            $reservation && $depositPaidAmount > 0 && ! $depositRequiredValue,
            422,
            __('reservation::messages.deposit_balance_must_be_settled_before_disabling'),
        );
        $depositCurrency = $reservation?->deposit_currency ?: $branch->currency;
        $depositCurrencyRate = $reservation && $depositPaidAmount > 0
            ? $reservation->getRawOriginal('deposit_currency_rate')
            : ReservationDepositCurrencyRate::toDefault($depositCurrency);
        $status = $this->statusForPayload($data, $reservation, $setting, $guestCount);

        return [
            'branch_id' => $branchId,
            'customer_id' => $data['customer_id'] ?? $reservation?->customer_id,
            'phone_country_iso_code' => $data['phone_country_iso_code'] ?? $reservation?->phone_country_iso_code,
            'reservation_number' => $reservation?->reservation_number
                ?? $this->numbers->generate($branchId, $data['reservation_date']),
            'reservation_type' => $reservationType->value,
            'status' => $status,
            'table_assignment_status' => $reservation?->table_assignment_status?->value ?? TableAssignmentStatus::Unassigned->value,
            'table_assignment_mode' => $this->assignmentMode($data, $reservation)->value,
            'reservation_date' => $data['reservation_date'] ?? $reservation?->reservation_date,
            'start_time' => $data['start_time'] ?? $reservation?->start_time,
            'end_time' => $data['end_time'] ?? $reservation?->end_time,
            'start_at' => $data['start_at'],
            'end_at' => $data['end_at'],
            'duration_minutes' => $duration,
            'guest_count' => $guestCount,
            'adult_count' => $data['adult_count'] ?? $reservation?->adult_count,
            'child_count' => $data['child_count'] ?? $reservation?->child_count,
            'customer_name' => $data['customer_name'] ?? $reservation?->customer_name,
            'customer_phone' => $data['customer_phone'] ?? $reservation?->customer_phone,
            'customer_email' => $data['customer_email'] ?? $reservation?->customer_email,
            'occasion' => $data['occasion'] ?? $reservation?->occasion,
            'special_requests' => $data['special_requests'] ?? $reservation?->special_requests,
            'internal_notes' => $data['internal_notes'] ?? $reservation?->internal_notes,
            'payment_status' => $this->paymentStatusForDeposit($data, $reservation, $depositRequiredValue, $depositAmount, $depositPaidAmount),
            'deposit_required' => $depositRequiredValue,
            'deposit_amount' => $depositAmount,
            'deposit_currency' => $depositCurrency,
            'deposit_currency_rate' => $depositCurrencyRate,
            'confirmed_at' => $reservation?->confirmed_at ?? ($status === ReservationStatus::Confirmed->value ? now() : null),
            'confirmed_by' => $reservation?->confirmed_by ?? ($status === ReservationStatus::Confirmed->value ? auth()->id() : null),
            'external_reference' => $data['external_reference'] ?? $reservation?->external_reference,
        ];
    }

    /**
     * Convert stored end-at to branch-local end time.
     */
    protected function endTimeFrom(string $endAt, int $branchId): string
    {
        /** @var Branch|null $branch */
        $branch = Branch::withoutGlobalActive()->find($branchId);

        return Carbon::parse($endAt)->setTimezone($branch?->timezone ?: config('app.timezone'))->format('H:i:s');
    }

    /**
     * Resolve reservation type from incoming data and current state.
     *
     * @param  array<string, mixed>  $data
     */
    protected function reservationTypeForPayload(array $data, ?Reservation $reservation = null): ReservationType
    {
        $value = $data['reservation_type'] ?? $reservation?->reservation_type ?? ReservationType::Standard;

        return $value instanceof ReservationType ? $value : ReservationType::from($value);
    }

    /**
     * Validate reservation policy settings that are not availability specific.
     *
     * @param  array<string, mixed>  $data
     */
    protected function assertSettingAllowsReservation(
        array $data,
        ?Reservation $reservation,
        ReservationSetting $setting,
        ReservationType $reservationType
    ): void {
        $creatingOrChangingType = is_null($reservation) || array_key_exists('reservation_type', $data);

        abort_if(
            $creatingOrChangingType && $reservationType === ReservationType::WalkIn && ! $setting->allow_walk_in,
            422,
            __('reservation::messages.walk_in_not_allowed')
        );
    }

    /**
     * Resolve payment status from explicit input or deposit amounts.
     *
     * @param  array<string, mixed>  $data
     */
    protected function paymentStatusForDeposit(
        array $data,
        ?Reservation $reservation,
        bool $depositRequired,
        float $depositAmount,
        float $depositPaidAmount
    ): string {
        if (! $depositRequired) {
            return ReservationPaymentStatus::NotRequired->value;
        }

        if (
            $reservation?->payment_status instanceof ReservationPaymentStatus
            && in_array($reservation->payment_status, [ReservationPaymentStatus::Refunded, ReservationPaymentStatus::Failed], true)
        ) {
            return $reservation->payment_status->value;
        }

        if ($depositPaidAmount <= 0) {
            return ReservationPaymentStatus::Unpaid->value;
        }

        if ($depositPaidAmount < $depositAmount) {
            return ReservationPaymentStatus::PartiallyPaid->value;
        }

        return ReservationPaymentStatus::Paid->value;
    }

    /** {@inheritDoc} */
    public function update(int|string $id, array $data): Reservation
    {
        return DB::transaction(function () use ($id, $data) {
            $reservation = Reservation::query()
                ->with('tables:id')
                ->where(fn (Builder $query) => $query->where('id', $id)->orWhere('reservation_number', $id))
                ->lockForUpdate()
                ->firstOrFail();
            abort_unless($reservation->status->allowsEditing(), 422, __('reservation::messages.reservation_cannot_edit_in_current_status'));

            $mode = $this->assignmentMode($data, $reservation);
            $payload = $this->payload($data, $reservation);
            $branchChanged = isset($data['branch_id']) && (int) $data['branch_id'] !== (int) $reservation->branch_id;
            $this->assertAvailableForUpdate($reservation, $payload, $data, $branchChanged, $mode);

            $reservation->update([...$payload, 'updated_by' => auth()->id()]);

            if ($mode === TableAssignmentMode::AssignNow) {
                $reservation = $this->assignment->assign($reservation, $this->assignmentTableIds($data, $reservation, $branchChanged));
            } elseif ($mode === TableAssignmentMode::AutoAssign) {
                $reservation = $this->assignment->autoAssign($reservation);
            } elseif ($mode === TableAssignmentMode::AssignLater || $branchChanged) {
                $reservation = $this->clearTables($reservation);
            }

            $reservation = $reservation->fresh(['tables', 'reservationTables.table', 'reservationTables.assignedBy', 'branch', 'createdBy', 'customer']);
            event(new ReservationUpdated($reservation));

            return $reservation;
        });
    }

    /** {@inheritDoc} */
    public function show(int|string $id): Reservation
    {
        return Reservation::query()
            ->with($this->showRelations())
            ->where(fn (Builder $query) => $query->where('id', $id)->orWhere('reservation_number', $id))
            ->firstOrFail();
    }

    /**
     * Get relationships required by reservation detail responses.
     *
     * @return array<int, string>
     */
    protected function showRelations(): array
    {
        return [
            'branch:id,name,timezone,currency',
            'reservationSetting:id,branch_id,deposit_refundable',
            'customer:id,name,email,phone,phone_country_iso_code',
            'createdBy:id,name',
            'tables:id,name,capacity,status,branch_id',
            'reservationTables.table:id,name,capacity,status,branch_id',
            'reservationTables.assignedBy:id,name',
            'statusLogs.changedBy:id,name',
            'reminders',
            'depositTransactions.order:id,reference_no',
            'depositTransactions.invoice:id,invoice_number',
            'depositTransactions.payment:id,transaction_id,voided_at',
            'depositTransactions.originalTransaction:id,type',
            'depositTransactions.reversedByTransaction:id,type',
            'depositTransactions.createdBy:id,name',
            'order:id,reference_no',
            'invoice:id,invoice_number',
        ];
    }

    /**
     * Validate an update against reservation availability rules.
     *
     * @param  array<string, mixed>  $payload
     * @param  array<string, mixed>  $data
     */
    protected function assertAvailableForUpdate(Reservation $reservation, array $payload, array $data, bool $branchChanged, TableAssignmentMode $mode): void
    {
        $tableIds = $mode === TableAssignmentMode::AssignNow
            ? $this->assignmentTableIds($data, $reservation, $branchChanged)
            : [];

        $check = $this->availability->check([
            'branch_id' => $payload['branch_id'],
            'reservation_date' => $payload['reservation_date'] instanceof Carbon
                ? $payload['reservation_date']->toDateString()
                : $payload['reservation_date'],
            'start_time' => $payload['start_time'],
            'duration_minutes' => $payload['duration_minutes'],
            'guest_count' => $payload['guest_count'],
            'table_assignment_mode' => $mode->value,
            'table_ids' => $tableIds,
            'exclude_reservation_id' => $reservation->id,
        ]);

        abort_unless($check['available'], 422, $check['warnings'][0] ?? __('reservation::messages.reservation_unavailable'));
    }

    /**
     * Resolve table ids for manual assignment.
     *
     * @param  array<string, mixed>  $data
     * @return array<int, int>
     */
    protected function assignmentTableIds(array $data, ?Reservation $reservation = null, bool $branchChanged = false): array
    {
        if (array_key_exists('table_ids', $data)) {
            return array_values(array_filter(array_map('intval', $data['table_ids'] ?? [])));
        }

        return $reservation && ! $branchChanged
            ? $reservation->tables->pluck('id')->map(fn ($id) => (int) $id)->values()->all()
            : [];
    }

    /**
     * Clear all table assignments.
     */
    protected function clearTables(Reservation $reservation): Reservation
    {
        $reservation->tables()->detach();
        $reservation->update(['table_assignment_status' => TableAssignmentStatus::Unassigned]);

        return $reservation->fresh(['tables', 'reservationTables.table', 'reservationTables.assignedBy', 'branch', 'createdBy', 'customer']);
    }

    /** {@inheritDoc} */
    public function destroy(int|string|array $ids): bool
    {
        return DB::transaction(function () use ($ids): bool {
            $reservations = Reservation::query()
                ->withCount('depositTransactions')
                ->whereIn('id', parseIds($ids))
                ->lockForUpdate()
                ->get();

            abort_if(
                $reservations->contains(fn (Reservation $reservation) => ! $reservation->status->isTerminal()),
                422,
                __('reservation::messages.active_reservation_cannot_delete'),
            );
            abort_if(
                $reservations->contains(fn (Reservation $reservation) => $reservation->deposit_transactions_count > 0),
                422,
                __('reservation::messages.financial_reservation_cannot_delete'),
            );

            return Reservation::query()->whereKey($reservations->modelKeys())->delete() ?: false;
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
        return Reservation::query()
            ->with([
                'branch:id,name,timezone,currency',
                'reservationSetting:id,branch_id,deposit_refundable',
                'createdBy:id,name',
                'tables:id,name',
                'reservationTables.table:id,name,capacity,status,branch_id',
            ])
            ->filters($filters)
            ->sortBy($sorts ?: [
                ['key' => 'reservation_date', 'order' => 'desc'],
                ['key' => 'start_at', 'order' => 'desc'],
            ]);
    }

    /** {@inheritDoc} */
    public function getStructureFilters(): array
    {
        $branchFilter = GlobalStructureFilters::branch();

        return [
            ...(is_null($branchFilter) ? [] : [$branchFilter]),
            [
                'key' => 'status',
                'label' => __('reservation::reservations.filters.status'),
                'type' => 'select',
                'options' => ReservationStatus::toArrayTrans(),
            ],
            [
                'key' => 'reservation_type',
                'label' => __('reservation::reservations.filters.reservation_type'),
                'type' => 'select',
                'options' => ReservationType::toArrayTrans(),
            ],
            GlobalStructureFilters::from(),
            GlobalStructureFilters::to(),
        ];
    }

    /** {@inheritDoc} */
    public function getFormMeta(?int $branchId = null, ?int $floorId = null, ?int $zoneId = null): array
    {
        $resolvedBranchId = $this->branchAccess->resolve($branchId);

        if (is_null($branchId)) {
            return [
                'branches' => Branch::list(true),
                'countries' => Country::toList(),
                'customers' => User::list(defaultRole: DefaultRole::Customer, withDetails: true),
                'statuses' => ReservationStatus::toArrayTrans(),
                'initial_statuses' => collect(ReservationStatus::toArrayTrans())
                    ->whereIn('id', [
                        ReservationStatus::Pending->value,
                        ReservationStatus::Confirmed->value,
                    ])
                    ->values()
                    ->all(),
                'types' => ReservationType::toArrayTrans(),
                'table_assignment_modes' => TableAssignmentMode::toArrayTrans(),
                ...$this->branchFormMeta($resolvedBranchId, $floorId, $zoneId),
            ];
        }

        return [
            ...$this->branchFormMeta($resolvedBranchId, $floorId, $zoneId),
        ];
    }

    /**
     * Get branch-dependent reservation form metadata.
     *
     * @return array<string, mixed>
     */
    protected function branchFormMeta(?int $branchId, ?int $floorId = null, ?int $zoneId = null): array
    {
        if (is_null($branchId)) {
            return [
                'floors' => [],
                'zones' => [],
                'tables' => [],
                'settings' => null,
            ];
        }

        return [
            'floors' => Floor::list($branchId),
            'zones' => is_null($floorId) ? [] : Zone::list($branchId, $floorId),
            'tables' => Table::list($branchId, $floorId, $zoneId),
            'settings' => $this->settings->forBranch($branchId),
        ];
    }

    /** {@inheritDoc} */
    public function calendar(array $filters = []): array
    {
        return $this->exportQuery($filters)
            ->with('tables:id,name')
            ->get()
            ->map(fn (Reservation $reservation) => [
                'id' => $reservation->id,
                'title' => "$reservation->reservation_number - $reservation->customer_name",
                'start' => $reservation->start_at?->toIso8601String(),
                'end' => $reservation->end_at?->toIso8601String(),
                'status' => $reservation->status->toTrans(),
                'guest_count' => $reservation->guest_count,
                'tables' => $reservation->tables->map(fn (Table $table) => ['id' => $table->id, 'name' => $table->name])->values(),
            ])
            ->values()
            ->all();
    }

    /** {@inheritDoc} */
    public function exportResourceKey(): string
    {
        return 'reservations';
    }

    /** {@inheritDoc} */
    public function exportPermission(): string
    {
        return 'admin.reservations.export';
    }

    /** {@inheritDoc} */
    public function exportModelClass(): string
    {
        return Reservation::class;
    }
}
