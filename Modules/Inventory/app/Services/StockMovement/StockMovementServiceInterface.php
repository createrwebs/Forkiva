<?php

namespace Modules\Inventory\Services\StockMovement;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Modules\Export\Contracts\TableExportSourceInterface;
use Modules\Inventory\Data\StockMovementCorrectionResult;
use Modules\Inventory\Data\StockMovementData;
use Modules\Inventory\Models\StockMovement;
use Throwable;

interface StockMovementServiceInterface extends TableExportSourceInterface
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
    public function getModel(): StockMovement;

    /**
     * Get specific resource
     *
     * @return StockMovement|Builder|EloquentCollection|array;
     *
     * @throws ModelNotFoundException
     */
    public function findOrFail(int $id): StockMovement|Builder|EloquentCollection|array;

    /**
     * Display a listing of the resource.
     */
    public function get(array $filters = [], array $sorts = []): LengthAwarePaginator;

    /**
     * Shared table query used by pagination and export.
     */
    public function exportQuery(array $filters = [], ?array $sorts = [], array $context = []): Builder;

    /**
     * Show the specified resource.
     *
     * @throws ModelNotFoundException
     */
    public function show(int $id): StockMovement;

    /**
     * Store a newly created resource in storage.
     *
     * @throws Throwable
     */
    public function store(array $data): StockMovement;

    /**
     * Record a trusted immutable stock movement and update operational balances.
     *
     * @throws Throwable
     */
    public function record(StockMovementData $data): StockMovement;

    /**
     * Reverse a committed movement using its exact frozen quantity and cost.
     *
     * @throws Throwable
     */
    public function reverse(
        StockMovement $movement,
        string $idempotencyKey,
        string $reason,
        bool $emitAccounting = true,
    ): StockMovement;

    /**
     * Correct a committed movement through an exact reversal and replacement.
     *
     * @throws Throwable
     */
    public function correct(int $id, int|float|string $quantity, string $reason): StockMovementCorrectionResult;

    /**
     * Get structure filters for frontend
     */
    public function getStructureFilters(?int $branchId = null): array;

    /**
     * Get form meta
     */
    public function getFormMeta(?int $branchId = null): array;
}
