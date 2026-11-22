<?php

namespace Modules\User\Services\Role;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use LogicException;
use Modules\Export\Contracts\TableExportSourceInterface;
use Modules\User\Models\Role;
use Spatie\Permission\Exceptions\RoleAlreadyExists;

interface RoleServiceInterface extends TableExportSourceInterface
{
    /**
     * Display a listing of the resource.
     */
    public function get(array $filters = [], ?array $sorts = []): LengthAwarePaginator;

    /**
     * Show the specified resource.
     *
     * @throws ModelNotFoundException
     */
    public function show(int $id): Role;

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
    public function getModel(): Role;

    /**
     * Get specific resource
     *
     * @return Role|Builder|EloquentCollection|array;
     *
     * @throws ModelNotFoundException
     */
    public function findOrFail(int $id): Role|Builder|EloquentCollection|array;

    /**
     * Get specific resource
     *
     * @return Role|Builder|EloquentCollection|array;
     */
    public function find(int $id): Role|Builder|EloquentCollection|array;

    /**
     * Store a newly created resource in storage.
     *
     * @throws RoleAlreadyExists
     */
    public function store(array $data): Role;

    /**
     * Update the specified resource in storage.
     *
     * @throws ModelNotFoundException
     */
    public function update(int $id, array $data): Role;

    /**
     * Destroy resource's by given id.
     *
     * @throws ModelNotFoundException
     * @throws LogicException
     */
    public function destroy(int|string|array $ids): bool;

    /**
     * Get permissions
     */
    public function getPermissions(): array;

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
