<?php

namespace Modules\Branch\Services\Branch;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use LogicException;
use Modules\Branch\Models\Branch;
use Modules\Export\Contracts\TableExportSourceInterface;
use Throwable;

interface BranchServiceInterface extends TableExportSourceInterface
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
    public function getModel(): Branch;

    /**
     * Get specific resource
     *
     * @return Branch|Builder|EloquentCollection|array;
     *
     * @throws ModelNotFoundException
     */
    public function findOrFail(int $id): Branch|Builder|EloquentCollection|array;

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
    public function show(int $id): Branch;

    /**
     * Store a newly created resource in storage.
     *
     * @throws Throwable
     */
    public function store(array $data): Branch;

    /**
     * Update the specified resource in storage.
     *
     * @throws ModelNotFoundException
     */
    public function update(int $id, array $data): Branch;

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
    public function getStructureFilters(): array;

    /**
     * Get form meta
     */
    public function getFormMeta(): array;
}
