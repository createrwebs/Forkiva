<?php

namespace Modules\Category\Services\CategoryTreeUpdater;

use Throwable;

interface CategoryTreeUpdaterServiceInterface
{
    /**
     * Update category tree order.
     *
     *
     * @throws Throwable
     */
    public function update(int $menuId, array $tree): void;

    /**
     * Apply menu-scoped parent changes while preserving the nested set and cache.
     */
    public function updateParents(int $menuId, array $parentIds): void;
}
