<?php

namespace Modules\SeatingPlan\Services\Table;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use LogicException;
use Modules\Export\Contracts\TableExportSourceInterface;
use Modules\SeatingPlan\Models\Table;

interface TableServiceInterface extends TableExportSourceInterface
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
    public function getModel(): Table;

    /**
     * Get specific resource
     *
     * @return Table|Builder|EloquentCollection|array;
     *
     * @throws ModelNotFoundException
     */
    public function findOrFail(int $id, bool $withBranch = false): Table|Builder|EloquentCollection|array;

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
    public function show(int $id): Table;

    /**
     * Store a newly created resource in storage.
     */
    public function store(array $data): Table;

    /**
     * Update the specified resource in storage.
     *
     * @throws ModelNotFoundException
     */
    public function update(int $id, array $data): Table;

    /**
     * Destroy resource's by given id.
     *
     * @throws ModelNotFoundException
     * @throws LogicException
     */
    public function destroy(int|array|string $ids): bool;

    /**
     * Get structure filters for frontend
     */
    public function getStructureFilters(?int $branchId = null, ?int $floorId = null): array;

    /**
     * Get form meta
     */
    public function getFormMeta(?int $branchId = null, ?int $floorId = null): array;

    /**
     * Get table status logs
     */
    public function getStatusLogs(int $id): LengthAwarePaginator;

    /**
     * Regenerate table QR token.
     */
    public function regenerateQrToken(int $id): Table;
}
