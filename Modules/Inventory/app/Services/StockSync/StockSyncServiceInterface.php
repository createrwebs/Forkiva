<?php

namespace Modules\Inventory\Services\StockSync;

use Illuminate\Support\Collection;
use Modules\Inventory\Data\StockSyncResult;
use Modules\Option\Models\OptionValue;
use Modules\Order\Models\Order;
use Modules\Product\Models\Ingredientable;
use Modules\Product\Models\Product;
use Throwable;

interface StockSyncServiceInterface
{
    /**
     * Capture the immutable ingredient snapshot used by a future deduction.
     *
     * @param  bool  $replaceExisting  Refresh the snapshot after an explicit order edit.
     *
     * @throws Throwable
     */
    public function snapshotOrderStock(Order $order, bool $replaceExisting = false): void;

    /**
     * Deduct the ingredients consumed by a completed order.
     *
     * @throws Throwable
     */
    public function deductOrderStock(Order $order): StockSyncResult;

    /**
     * Restore stock from the order's original deduction movements.
     *
     * @throws Throwable
     */
    public function restoreOrderStock(Order $order): StockSyncResult;

    /**
     * Resolve the effective product bill of materials after option modifiers.
     *
     * @param  Collection<int, OptionValue>  $values
     * @return Collection<int, Ingredientable>
     */
    public function resolveProductBom(Product $product, Collection $values, int $branchId): Collection;
}
