<?php

namespace Modules\Loyalty\Services\LoyaltyCustomer;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Modules\Export\Contracts\TableExportSourceInterface;
use Modules\Loyalty\Models\LoyaltyCustomer;

interface LoyaltyCustomerServiceInterface extends TableExportSourceInterface
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
    public function getModel(): LoyaltyCustomer;

    /**
     * Get specific resource
     *
     * @return LoyaltyCustomer|Builder|EloquentCollection|array;
     *
     * @throws ModelNotFoundException
     */
    public function findOrFail(int $id): LoyaltyCustomer|Builder|EloquentCollection|array;

    /**
     * Display a listing of the resource.
     */
    public function get(array $filters = [], array $sorts = []): LengthAwarePaginator;

    /**
     * Show the specified resource.
     *
     * @throws ModelNotFoundException
     */
    public function show(int $id): LoyaltyCustomer;

    /**
     * Get structure filters for frontend
     */
    public function getStructureFilters(?int $programId = null): array;

    /**
     * Shared table query used by pagination and export.
     */
    public function exportQuery(array $filters = [], ?array $sorts = [], array $context = []): Builder;
}
