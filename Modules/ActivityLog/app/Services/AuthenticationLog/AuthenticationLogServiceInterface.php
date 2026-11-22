<?php

namespace Modules\ActivityLog\Services\AuthenticationLog;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Modules\ActivityLog\Models\AuthenticationLog;
use Modules\Export\Contracts\TableExportSourceInterface;

interface AuthenticationLogServiceInterface extends TableExportSourceInterface
{
    /**
     * Display a listing of the resource.
     */
    public function get(array $filters, ?array $sorts = []): LengthAwarePaginator;

    /**
     * Show the specified resource.
     *
     * @throws ModelNotFoundException
     */
    public function show(int $id): AuthenticationLog;

    /**
     * Shared table query used by pagination and export.
     */
    public function exportQuery(array $filters = [], ?array $sorts = [], array $context = []): Builder;
}
