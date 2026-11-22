<?php

namespace Modules\Inventory\Services\StockTransfer;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Modules\Export\Contracts\TableExportSourceInterface;
use Modules\Inventory\Models\StockTransfer;
use Modules\Inventory\Models\StockTransferReceipt;
use Throwable;

interface StockTransferServiceInterface extends TableExportSourceInterface
{
    /**
     * Get the translated stock-transfer resource label.
     */
    public function label(): string;

    /**
     * Paginate branch-visible stock transfers.
     *
     * @param  array<string, mixed>  $filters
     * @param  array<int, array<string, mixed>>|null  $sorts
     */
    public function get(array $filters = [], ?array $sorts = []): LengthAwarePaginator;

    /**
     * Build the branch-safe stock-transfer export query.
     *
     * @param  array<string, mixed>  $filters
     * @param  array<int, array<string, mixed>>|null  $sorts
     * @param  array<string, mixed>  $context
     * @return Builder<StockTransfer>
     */
    public function exportQuery(array $filters = [], ?array $sorts = [], array $context = []): Builder;

    /**
     * Retrieve one branch-visible transfer with its complete audit graph.
     */
    public function show(int|string $id): StockTransfer;

    /**
     * Create a draft transfer and its explicitly mapped ingredient lines.
     *
     * @param  array<string, mixed>  $data
     *
     * @throws Throwable
     */
    public function store(array $data): StockTransfer;

    /**
     * Update a draft transfer and replace its editable lines.
     *
     * @param  array<string, mixed>  $data
     *
     * @throws Throwable
     */
    public function update(int|string $id, array $data): StockTransfer;

    /**
     * Approve and freeze a valid draft transfer.
     *
     *
     * @throws Throwable
     */
    public function approve(int|string $id): StockTransfer;

    /**
     * Dispatch every approved line and create frozen-cost outbound movements.
     *
     *
     * @throws Throwable
     */
    public function dispatch(int|string $id): StockTransfer;

    /**
     * Create an idempotent partial or final receipt.
     *
     * @param  array<string, mixed>  $data
     *
     * @throws Throwable
     */
    public function receive(int|string $id, array $data): StockTransferReceipt;

    /**
     * Close all remaining quantities as a discrepancy.
     *
     *
     * @throws Throwable
     */
    public function close(int|string $id, string $reason): StockTransfer;

    /**
     * Cancel a draft or approved transfer before dispatch.
     *
     *
     * @throws Throwable
     */
    public function cancel(int|string $id, string $reason): StockTransfer;

    /**
     * Void a dispatched transfer before its first receipt.
     *
     *
     * @throws Throwable
     */
    public function void(int|string $id, string $reason): StockTransfer;

    /**
     * Get branch and ingredient options for the transfer form.
     *
     * @return array<string, mixed>
     */
    public function getFormMeta(
        ?int $sourceBranchId = null,
        ?int $destinationBranchId = null,
        array $sourceIngredientIds = [],
        array $destinationIngredientIds = [],
    ): array;

    /**
     * Search bounded source or destination ingredient options.
     *
     * @param  array<int, int>  $ids
     * @return Collection<int, array<string, mixed>>
     */
    public function getIngredientOptions(
        int $branchId,
        string $role,
        ?string $search = null,
        array $ids = [],
        ?int $unitId = null,
        ?int $sourceBranchId = null,
    ): Collection;

    /**
     * Get SmartDataTable filter definitions.
     *
     * @return array<int, array<string, mixed>>
     */
    public function getStructureFilters(): array;
}
