<?php

namespace Modules\Reservation\Services\ReservationSetting;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Modules\Export\Contracts\TableExportSourceInterface;
use Modules\Reservation\Models\ReservationSetting;

interface ReservationSettingServiceInterface extends TableExportSourceInterface
{
    /**
     * Get resource label.
     */
    public function label(): string;

    /**
     * Paginate reservation settings.
     *
     * @param  array<string, mixed>  $filters
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
     * Show one setting.
     */
    public function show(int $id): ReservationSetting;

    /**
     * Store or upsert branch reservation setting.
     *
     * @param  array<string, mixed>  $data
     */
    public function store(array $data): ReservationSetting;

    /**
     * Update reservation setting.
     *
     * @param  array<string, mixed>  $data
     */
    public function update(int $id, array $data): ReservationSetting;

    /**
     * Delete reservation settings.
     *
     * @param  int|string|array<int>  $ids
     */
    public function destroy(int|string|array $ids): bool;

    /**
     * Get or create settings for a branch.
     */
    public function forBranch(int $branchId): ReservationSetting;

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
    public function getFormMeta(): array;
}
