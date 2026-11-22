<?php

namespace Modules\Pos\Services\PosViewer;

use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Pagination\LengthAwarePaginator;

interface PosViewerServiceInterface
{
    /**
     * Get POS Screen configuration
     */
    public function getConfiguration(?int $branchId = null): array;

    /**
     * Get Menu categories.
     */
    public function getMenuItems(int $menuId): array;

    /**
     * Get tree categories
     */
    public function getCategories(int $menuId): AnonymousResourceCollection;

    /**
     * Get paginated products.
     *
     * @param  array<string, mixed>  $filters
     */
    public function getProducts(int $menuId, array $filters = []): LengthAwarePaginator;

    /**
     * Get all products for a menu.
     */
    public function getAllProducts(int $menuId): AnonymousResourceCollection;

    /**
     * Get paginated customers for POS lookup.
     *
     * @param  array<string, mixed>  $filters
     */
    public function getCustomers(array $filters = []): LengthAwarePaginator;
}
