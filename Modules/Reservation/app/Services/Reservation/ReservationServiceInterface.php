<?php

namespace Modules\Reservation\Services\Reservation;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Modules\Export\Contracts\TableExportSourceInterface;
use Modules\Reservation\Models\Reservation;
use Throwable;

interface ReservationServiceInterface extends TableExportSourceInterface
{
    /**
     * Get resource label.
     */
    public function label(): string;

    /**
     * Get model class.
     *
     * @return class-string<Reservation>
     */
    public function model(): string;

    /**
     * Get model instance.
     */
    public function getModel(): Reservation;

    /**
     * Paginate reservations.
     *
     * @param  array<string, mixed>  $filters
     */
    public function get(array $filters = [], ?array $sorts = []): LengthAwarePaginator;

    /**
     * Show reservation.
     */
    public function show(int|string $id): Reservation;

    /**
     * Store reservation.
     *
     * @param  array<string, mixed>  $data
     *
     * @throws Throwable
     */
    public function store(array $data): Reservation;

    /**
     * Update reservation.
     *
     * @param  array<string, mixed>  $data
     *
     * @throws Throwable
     */
    public function update(int|string $id, array $data): Reservation;

    /**
     * Delete reservations.
     *
     * @param  int|string|array<int>  $ids
     *
     * @throws Throwable
     */
    public function destroy(int|string|array $ids): bool;

    /**
     * Get structured listing filters.
     *
     * @return array<int, array<string, mixed>>
     */
    public function getStructureFilters(): array;

    /**
     * Get form metadata.
     *
     * @return array<string, mixed>
     */
    public function getFormMeta(?int $branchId = null, ?int $floorId = null, ?int $zoneId = null): array;

    /**
     * Get calendar records.
     *
     * @param  array<string, mixed>  $filters
     * @return array<int, array<string, mixed>>
     */
    public function calendar(array $filters = []): array;

    /**
     * Shared query used by table and export.
     *
     * @param  array<string, mixed>  $filters
     * @param  array<string, mixed>  $context
     */
    public function exportQuery(array $filters = [], ?array $sorts = [], array $context = []): Builder;
}
