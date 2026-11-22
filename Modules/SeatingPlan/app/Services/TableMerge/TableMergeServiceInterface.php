<?php

namespace Modules\SeatingPlan\Services\TableMerge;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Modules\Export\Contracts\TableExportSourceInterface;
use Modules\SeatingPlan\Models\TableMerge;

interface TableMergeServiceInterface extends TableExportSourceInterface
{
    /**
     * Model for the resource.
     */
    public function model(): string;

    /**
     * Get a new instance of the model.
     */
    public function getModel(): TableMerge;

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
    public function show(int $id): TableMerge;

    /**
     * Get structure filters for frontend
     */
    public function getStructureFilters(?int $branchId = null): array;
}
