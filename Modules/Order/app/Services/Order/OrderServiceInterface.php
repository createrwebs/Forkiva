<?php

namespace Modules\Order\Services\Order;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Modules\Export\Contracts\TableExportSourceInterface;
use Modules\Order\Enums\OrderStatus;
use Modules\Order\Models\Order;
use Modules\Printer\Enum\PrintContentType;
use Throwable;

interface OrderServiceInterface extends TableExportSourceInterface
{
    /**
     * Label for the resource.
     */
    public function label(): string;

    /**
     * Model for the resource.
     */
    public function model(): string;

    /**
     * Get a new instance of the model.
     */
    public function getModel(): Order;

    /**
     * Display a listing of the resource.
     */
    public function get(array $filters = [], array $sorts = []): LengthAwarePaginator;

    /**
     * Show the specified resource.
     *
     * @throws ModelNotFoundException
     */
    public function show(int|string $id, ?array $with = null): Order;

    /**
     * Get structure filters for frontend
     */
    public function getStructureFilters(): array;

    /**
     * Cancel order
     *
     * @throws Throwable
     */
    public function cancel(int|string $id, array $data): void;

    /**
     * Refund order
     *
     * @throws Throwable
     */
    public function refund(int|string $id, array $data): void;

    /**
     * Init edit order
     *
     * @throws Throwable
     */
    public function initEdit(int|string $id): array;

    /**
     * Get specific resource
     *
     * @return Order|Builder|EloquentCollection|array;
     *
     * @throws ModelNotFoundException
     */
    public function findOrFail(int|string $id, bool $withBranch = false): Order|Builder|EloquentCollection|array;

    /**
     * Get update status meta
     */
    public function getUpdateStatusMeta(int|string $id): array;

    /**
     * Get active orders
     */
    public function activeOrders(?int $branchId = null, array $filters = []): LengthAwarePaginator;

    /**
     * Get upcoming orders
     */
    public function upcomingOrders(?int $branchId = null, array $filters = []): LengthAwarePaginator;

    /**
     * Move to next status
     *
     * @throws Throwable
     */
    public function moveToNextStatus(string|int $id): OrderStatus;

    /**
     * Kitchen move to next status
     *
     * @throws Throwable
     */
    public function kitchenMoveToNextStatus(string|int $id): OrderStatus;

    /**
     * Preview print
     *
     * @throws Throwable
     */
    public function previewPrint(
        string|int $id,
        PrintContentType $type,
        ?int $kitchenId = null,
        ?int $specificId = null,
        string $contentType = 'html'
    ): array;

    /**
     * Get print meta
     *
     * @throws Throwable
     */
    public function printMeta(string|int $id, ?int $branchId = null, ?int $registerId = null): array;

    /**
     * Shared table query used by pagination and export.
     */
    public function exportQuery(array $filters = [], ?array $sorts = [], array $context = []): Builder;
}
