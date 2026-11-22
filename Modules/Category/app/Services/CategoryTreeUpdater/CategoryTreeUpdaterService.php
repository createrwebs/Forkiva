<?php

namespace Modules\Category\Services\CategoryTreeUpdater;

use Arr;
use Cache;
use DB;
use Modules\Category\Models\Category;

class CategoryTreeUpdaterService implements CategoryTreeUpdaterServiceInterface
{
    /** {@inheritDoc} */
    public function updateParents(int $menuId, array $parentIds): void
    {
        if ($parentIds === []) {
            return;
        }

        DB::transaction(function () use ($menuId, $parentIds): void {
            $categories = Category::query()
                ->withoutGlobalActive()
                ->where('menu_id', $menuId)
                ->whereIn('id', array_keys($parentIds))
                ->lockForUpdate()
                ->get()
                ->keyBy('id');

            abort_unless($categories->count() === count($parentIds), 404);

            $parentIdsInMenu = Category::query()
                ->withoutGlobalActive()
                ->where('menu_id', $menuId)
                ->whereIn('id', array_values(array_filter($parentIds, fn ($id) => $id !== null)))
                ->pluck('id')
                ->map(fn ($id) => (int) $id)
                ->all();

            foreach ($parentIds as $parentId) {
                abort_unless($parentId === null || in_array((int) $parentId, $parentIdsInMenu, true), 404);
            }

            foreach ($parentIds as $categoryId => $parentId) {
                if ($categories[$categoryId]->parent_id !== $parentId) {
                    DB::table('categories')
                        ->where('id', $categoryId)
                        ->where('menu_id', $menuId)
                        ->update(['parent_id' => $parentId, 'updated_at' => now()]);
                }
            }

            Category::query()
                ->withoutGlobalActive()
                ->where('menu_id', $menuId)
                ->fixTree();
        });

        Cache::tags('categories')->flush();
    }

    /** {@inheritDoc} */
    public function update(int $menuId, array $tree): void
    {
        if ($tree === []) {
            return;
        }

        [$ids, $parentIdCases, $orderCases, $params] = static::getDataForQuery($tree);

        $grammar = DB::connection()->getQueryGrammar();
        $table = $grammar->wrapTable('categories');
        $id = $grammar->wrap('id');
        $parent = $grammar->wrap('parent_id');
        $order = $grammar->wrap('order');
        $updated = $grammar->wrap('updated_at');
        $menu = $grammar->wrap('menu_id');
        $sql = "UPDATE $table SET $parent = CASE $id $parentIdCases END, $order = CASE $id $orderCases END, $updated = ? WHERE $id IN ($ids) AND $menu = ?";

        $params[] = $menuId;

        DB::transaction(function () use ($sql, $params, $menuId) {
            DB::update($sql, $params);

            Category::query()->where('menu_id', $menuId)->fixTree();
        });

        Cache::tags('categories')->flush();
    }

    /**
     * Get data for update query.
     */
    private function getDataForQuery(array $tree): array
    {
        $params = [];
        $ids = [];
        $cases = [];

        foreach (static::getAttributesList($tree) as $id => $values) {
            foreach ($values as $column => $value) {
                // PostgreSQL must also infer numeric types when every parent is null.
                $placeholder = DB::getDriverName() === 'pgsql' ? 'CAST(? AS BIGINT)' : '?';
                $cases[$column][] = 'WHEN '.(int) $id." THEN $placeholder";
                $params[$column][] = $value;
            }

            $ids[] = (int) $id;
        }

        return $this->prepareData($ids, $cases, $params);
    }

    /**
     * Get attributes list from the given tree.
     */
    private function getAttributesList(array $tree): array
    {
        $attributes = [];

        foreach ($tree as $order => $category) {
            $attributes[$category['id']] = [
                'parent_id' => $category['parent'],
                'order' => $order,
            ];
        }

        return $attributes;
    }

    /**
     * Prepare data for update query.
     */
    private static function prepareData(array $ids, array $cases, array $params): array
    {
        $ids = implode(',', $ids);
        $parentIdCases = implode(' ', $cases['parent_id']);
        $orderCases = implode(' ', $cases['order']);

        $params = Arr::flatten($params);
        $params[] = now();

        return [$ids, $parentIdCases, $orderCases, $params];
    }
}
