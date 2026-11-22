<?php

namespace Modules\ActivityLog\Services\ActivityLog;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Modules\ActivityLog\Models\ActivityLog;
use Modules\Export\Contracts\TableExportSourceInterface;

interface ActivityLogServiceInterface extends TableExportSourceInterface
{
    /**
     * Display a listing of the resource.
     */
    public function get(array $filters = [], ?array $sorts = []): LengthAwarePaginator;

    /**
     * Get structure filters for frontend
     */
    public function getStructureFilters(): array;

    /**
     * Show the specified resource.
     *
     * @throws ModelNotFoundException
     */
    public function show(int $id): ActivityLog;

    /**
     * Shared table query used by pagination and export.
     */
    public function exportQuery(array $filters = [], ?array $sorts = [], array $context = []): Builder;
}
