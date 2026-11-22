<?php

namespace Modules\Order\Support;

use Illuminate\Support\Facades\DB;
use Modules\Order\Models\Order;
use Modules\SeatingPlan\Models\Table;
use Throwable;

final class OrderWriteLock
{
    /**
     * Lock tables before orders and reject ownership changes before executing the mutation.
     *
     * @throws Throwable
     */
    public static function run(int|string $id, callable $callback): mixed
    {
        $order = Order::query()->whereIdOrReference($id)->firstOrFail();
        $orders = Order::query()
            ->when(
                $order->table_merge_id,
                fn ($query) => $query->where('table_merge_id', $order->table_merge_id),
                fn ($query) => $query->whereKey($order->id),
            )
            ->orderBy('id')
            ->get();
        $tableIds = $orders->pluck('table_id')->filter()->unique()->sort()->values();

        // Discover ownership before the transaction so MySQL's first consistent read follows the locks.
        return DB::transaction(function () use ($orders, $tableIds, $callback) {
            if ($tableIds->isNotEmpty()) {
                Table::query()->whereIn('id', $tableIds)->orderBy('id')->lockForUpdate()->get();
            }
            $locked = Order::query()->whereIn('id', $orders->modelKeys())->orderBy('id')->lockForUpdate()->get();
            abort_unless(
                $locked->map->only(['id', 'table_id', 'table_merge_id'])->all()
                    === $orders->map->only(['id', 'table_id', 'table_merge_id'])->all(),
                409,
                __('order::checkout.order_changed'),
            );

            return $callback();
        }, 3);
    }
}
