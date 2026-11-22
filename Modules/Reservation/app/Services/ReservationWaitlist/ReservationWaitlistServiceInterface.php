<?php

namespace Modules\Reservation\Services\ReservationWaitlist;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Modules\Export\Contracts\TableExportSourceInterface;
use Modules\Reservation\Models\Reservation;
use Modules\Reservation\Models\ReservationWaitlist;
use Throwable;

interface ReservationWaitlistServiceInterface extends TableExportSourceInterface
{
    /**
     * Get a localized service label for the reservation waitlist module.
     */
    public function label(): string;

    /**
     * Retrieve a paginated list of reservation waitlist entries.
     *
     * @param  array<string, mixed>  $filters
     * @param  array<string, mixed>|null  $sorts
     */
    public function get(array $filters = [], ?array $sorts = []): LengthAwarePaginator;

    /**
     * Shared query used by table and export.
     *
     * @param  array<string, mixed>  $filters
     * @param  array<string, mixed>  $context
     */
    public function exportQuery(array $filters = [], ?array $sorts = [], array $context = []): Builder;

    /**
     * Get a single waitlist entry by id.
     */
    public function show(int $id): ReservationWaitlist;

    /**
     * Create a new reservation waitlist entry.
     *
     * @param  array<string, mixed>  $data
     */
    public function store(array $data): ReservationWaitlist;

    /**
     * Update a reservation waitlist entry.
     *
     * @param  array<string, mixed>  $data
     *
     * @throws Throwable
     */
    public function update(int $id, array $data): ReservationWaitlist;

    /**
     * Delete one or more waitlist entries.
     *
     * @param  int|string|array<int>  $ids
     *
     * @throws Throwable
     */
    public function destroy(int|string|array $ids): bool;

    /**
     * Trigger notification flow for the given waitlist entry.
     *
     * @throws Throwable
     */
    public function notify(int $id): ReservationWaitlist;

    /**
     * Convert a waitlist entry into a reservation.
     *
     * @param  array<string, mixed>  $data
     *
     * @throws Throwable
     */
    public function convert(int $id, array $data): Reservation;

    /**
     * Cancel a waitlist entry.
     *
     * @param  array<string, mixed>  $data
     *
     * @throws Throwable
     */
    public function cancel(int $id, array $data = []): ReservationWaitlist;

    /**
     * Get available filter structures for listing and searching.
     *
     * @return array<int, array<string, mixed>>
     */
    public function getStructureFilters(): array;

    /**
     * Get waitlist form metadata.
     *
     * @return array<string, mixed>
     */
    public function getFormMeta(?int $branchId = null): array;
}
