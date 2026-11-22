<?php

namespace Modules\Printer\Services\Agent;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use LogicException;
use Modules\Export\Contracts\TableExportSourceInterface;
use Modules\Printer\Models\PrintAgent;
use Modules\Printer\Models\Printer;

interface PrintAgentServiceInterface extends TableExportSourceInterface
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
     *
     * @return Printer
     */
    public function getModel(): PrintAgent;

    /**
     * Get specific resource
     *
     * @return PrintAgent|Builder|EloquentCollection|array;
     *
     * @throws ModelNotFoundException
     */
    public function findOrFail(int $id): PrintAgent|Builder|EloquentCollection|array;

    /**
     * Display a listing of the resource.
     */
    public function get(array $filters = [], array $sorts = []): LengthAwarePaginator;

    /**
     * Show the specified resource.
     *
     * @throws ModelNotFoundException
     */
    public function show(int $id): PrintAgent;

    /**
     * Store a newly created resource in storage.
     */
    public function store(array $data): PrintAgent;

    /**
     * Update the specified resource in storage.
     *
     * @throws ModelNotFoundException
     */
    public function update(int $id, array $data): PrintAgent;

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
    public function getFormMeta(?int $branchId = null): array;

    /**
     * Shared table query used by pagination and export.
     */
    public function exportQuery(array $filters = [], ?array $sorts = [], array $context = []): Builder;
}
