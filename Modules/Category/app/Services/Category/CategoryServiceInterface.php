<?php

namespace Modules\Category\Services\Category;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use LogicException;
use Modules\Category\Models\Category;
use Modules\Export\Contracts\TableExportSourceInterface;

interface CategoryServiceInterface extends TableExportSourceInterface
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
    public function getModel(): Category;

    /**
     * Get specific resource
     *
     * @return Category|Builder|EloquentCollection|array;
     *
     * @throws ModelNotFoundException
     */
    public function findOrFail(int $id): Category|Builder|EloquentCollection|array;

    /**
     * Display a listing of the resource.
     */
    public function getForTree(array $filters = []): Collection;

    /**
     * Shared export query.
     */
    public function exportQuery(array $filters = [], ?array $sorts = [], array $context = []): Builder;

    /**
     * Show the specified resource.
     *
     * @throws ModelNotFoundException
     */
    public function show(int $id): Category;

    /**
     * Store a newly created resource in storage.
     */
    public function store(array $data): Category;

    /**
     * Update the specified resource in storage.
     *
     * @throws ModelNotFoundException
     */
    public function update(int $id, array $data): Category;

    /**
     * Destroy resource's by given id.
     *
     * @throws ModelNotFoundException
     * @throws LogicException
     */
    public function destroy(int|array|string $ids): bool;
}
