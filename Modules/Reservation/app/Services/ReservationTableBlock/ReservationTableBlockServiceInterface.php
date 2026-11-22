<?php

namespace Modules\Reservation\Services\ReservationTableBlock;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Modules\Export\Contracts\TableExportSourceInterface;
use Modules\Reservation\Models\ReservationTableBlock;

interface ReservationTableBlockServiceInterface extends TableExportSourceInterface
{
    /**
     * Get a localized service label for table block operations.
     */
    public function label(): string;

    /**
     * Retrieve a paginated list of table block records.
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
     * Get a single table block by id.
     */
    public function show(int $id): ReservationTableBlock;

    /**
     * Create a new table block entry.
     *
     * @param  array<string, mixed>  $data
     */
    public function store(array $data): ReservationTableBlock;

    /**
     * Update an existing table block entry.
     *
     * @param  array<string, mixed>  $data
     */
    public function update(int $id, array $data): ReservationTableBlock;

    /**
     * Delete one or more table block records.
     *
     * @param  int|string|array<int>  $ids
     */
    public function destroy(int|string|array $ids): bool;

    /**
     * Get available filter structures for listing and searching.
     *
     * @return array<int, array<string, mixed>>
     */
    public function getStructureFilters(): array;
}
