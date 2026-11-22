<?php

namespace Modules\Loyalty\Services\LoyaltyProgram;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use LogicException;
use Modules\Export\Contracts\TableExportSourceInterface;
use Modules\Loyalty\Models\LoyaltyProgram;

interface LoyaltyProgramServiceInterface extends TableExportSourceInterface
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
    public function getModel(): LoyaltyProgram;

    /**
     * Get specific resource
     *
     * @return LoyaltyProgram|Builder|EloquentCollection|array;
     *
     * @throws ModelNotFoundException
     */
    public function findOrFail(int $id): LoyaltyProgram|Builder|EloquentCollection|array;

    /**
     * Display a listing of the resource.
     */
    public function get(array $filters = [], array $sorts = []): LengthAwarePaginator;

    /**
     * Show the specified resource.
     *
     * @throws ModelNotFoundException
     */
    public function show(int $id): LoyaltyProgram;

    /**
     * Store a newly created resource in storage.
     */
    public function store(array $data): LoyaltyProgram;

    /**
     * Update the specified resource in storage.
     *
     * @throws ModelNotFoundException
     */
    public function update(int $id, array $data): LoyaltyProgram;

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

    /**
     * Shared table query used by pagination and export.
     */
    public function exportQuery(array $filters = [], ?array $sorts = [], array $context = []): Builder;
}
