<?php

namespace Modules\Product\Services\ProductIngredient;

use Throwable;

interface ProductIngredientServiceInterface
{
    /**
     * Sync ingredients for a Product.
     *
     * @throws Throwable
     */
    public function syncForProduct(int $productId, int $branchId, array $items): void;

    /**
     * Sync ingredients for an Option Value.
     *
     * @throws Throwable
     */
    public function syncForOptionValue(int $optionValueId, int $branchId, array $items): void;
}
