<?php

namespace Modules\SeatingPlan\Services\TableViewer;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Modules\SeatingPlan\Models\Table;
use Throwable;

interface TableViewerServiceInterface
{
    /**
     * Get specific resource
     *
     * @return Table|Builder|EloquentCollection|array;
     *
     * @throws ModelNotFoundException
     */
    public function findOrFail(int $id): Table|Builder|EloquentCollection|array;

    /**
     * Display a listing of the resource.
     */
    public function get(?int $branchId = null, array $filters = []): array;

    /**
     * Show the specified resource.
     */
    public function show(int $id): Table;

    /**
     * Assign waiter
     */
    public function assignWaiter(int $id, array $data): void;

    /**
     * Make table as available
     */
    public function makeAsAvailable(int $id): void;

    /**
     * Merge Tables
     *
     * @throws Throwable
     */
    public function merge(int $id, array $data): void;

    /**
     * Get merge tables meta
     */
    public function getMergeMeta(int $id): array;

    /**
     * Split tables merged
     *
     * @throws Throwable
     */
    public function splitTable(int $mergeId): void;
}
