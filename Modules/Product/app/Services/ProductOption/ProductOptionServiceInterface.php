<?php

namespace Modules\Product\Services\ProductOption;

use Modules\Product\Models\Product;

interface ProductOptionServiceInterface
{
    /**
     * Sync Product Options.
     */
    public function syncOptions(Product $product, array $options): void;
}
