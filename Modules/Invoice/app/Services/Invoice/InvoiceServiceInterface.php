<?php

namespace Modules\Invoice\Services\Invoice;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Modules\Export\Contracts\TableExportSourceInterface;
use Modules\Invoice\Models\Invoice;

interface InvoiceServiceInterface extends TableExportSourceInterface
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
    public function getModel(): Invoice;

    /**
     * Display a listing of the resource.
     */
    public function get(array $filters = [], array $sorts = []): LengthAwarePaginator;

    /**
     * Show the specified resource.
     *
     * @throws ModelNotFoundException
     */
    public function show(int|string $id): Invoice;

    /**
     * Get structure filters for frontend
     */
    public function getStructureFilters(): array;

    /**
     * Shared table query used by pagination and export.
     */
    public function exportQuery(array $filters = [], ?array $sorts = [], array $context = []): Builder;
}
