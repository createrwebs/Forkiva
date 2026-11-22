<?php

namespace Modules\Inventory\Services\InventoryCount;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Modules\Export\Contracts\TableExportSourceInterface;
use Modules\Inventory\Models\InventoryCount;
use Throwable;

interface InventoryCountServiceInterface extends TableExportSourceInterface
{
    /**
     * Get the translated resource label.
     */
    public function label(): string;

    /**
     * Paginate inventory counts.
     *
     * @param  array<string, mixed>  $filters
     * @param  array<int, array<string, mixed>>|null  $sorts
     */
    public function get(array $filters = [], ?array $sorts = []): LengthAwarePaginator;

    /**
     * Build the branch-safe export query.
     *
     * @param  array<string, mixed>  $filters
     * @param  array<int, array<string, mixed>>|null  $sorts
     * @param  array<string, mixed>  $context
     */
    public function exportQuery(array $filters = [], ?array $sorts = [], array $context = []): Builder;

    /**
     * Retrieve one branch-authorized inventory count with its audit relations.
     */
    public function show(int $id): InventoryCount;

    /**
     * Create a draft inventory count and capture its stock snapshot.
     *
     * @param  array<string, mixed>  $data
     *
     * @throws Throwable
     */
    public function store(array $data): InventoryCount;

    /**
     * Update a draft inventory count and refresh its item snapshots.
     *
     * @param  array<string, mixed>  $data
     *
     * @throws Throwable
     */
    public function update(int $id, array $data): InventoryCount;

    /**
     * Approve a count, reject stale snapshots, and create stock adjustments.
     *
     * @throws Throwable
     */
    public function approve(int $id): InventoryCount;

    /**
     * Cancel an editable draft count.
     *
     * @throws Throwable
     */
    public function cancel(int $id, ?string $reason = null): InventoryCount;

    /**
     * Delete an editable draft count.
     */
    public function destroy(int|string|array $ids): bool;

    /**
     * Get form metadata for the resolved branch.
     *
     * @return array<string, mixed>
     */
    public function getFormMeta(?int $branchId = null): array;

    /**
     * Get table filter definitions.
     *
     * @return array<int, array<string, mixed>>
     */
    public function getStructureFilters(): array;
}
