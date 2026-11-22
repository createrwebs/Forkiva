<?php

namespace Modules\Pos\Services\PosSession;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Modules\Export\Contracts\TableExportSourceInterface;
use Modules\Pos\Models\PosSession;
use Throwable;

interface PosSessionServiceInterface extends TableExportSourceInterface
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
    public function getModel(): PosSession;

    /**
     * Get specific resource
     *
     * @return PosSession|Builder|EloquentCollection|array;
     *
     * @throws ModelNotFoundException
     */
    public function findOrFail(int $id): PosSession|Builder|EloquentCollection|array;

    /**
     * Display a listing of the resource.
     */
    public function get(array $filters = [], array $sorts = []): LengthAwarePaginator;

    /**
     * Show the specified resource.
     *
     * @throws ModelNotFoundException
     */
    public function show(int $id): PosSession;

    /**
     * Open pos sessions.
     *
     * @throws Throwable
     */
    public function open(array $data): PosSession;

    /**
     * Preview current reconciliation without closing the session or posting adjustments.
     *
     * @return array{session: PosSession, review: array}
     *
     * @throws Throwable
     */
    public function closingPreview(int $id, ?string $declaredCash = null): array;

    /**
     * Close pos sessions
     *
     * @throws ModelNotFoundException
     * @throws Throwable
     */
    public function close(int $id, array $data): PosSession;

    /**
     * Get structure filters for frontend
     */
    public function getStructureFilters(?int $branchId = null): array;

    /**
     * Get form meta
     */
    public function getFormMeta(?int $branchId = null): array;

    /**
     * Shared table query used by pagination and export.
     */
    public function exportQuery(array $filters = [], ?array $sorts = [], array $context = []): Builder;
}
