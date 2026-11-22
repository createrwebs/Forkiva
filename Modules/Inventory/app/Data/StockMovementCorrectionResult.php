<?php

namespace Modules\Inventory\Data;

use Modules\Inventory\Models\StockMovement;

/**
 * Carries the immutable movements created by a stock correction.
 */
final readonly class StockMovementCorrectionResult
{
    /**
     * Create a stock movement correction result.
     *
     * @param  StockMovement  $original  Original committed movement.
     * @param  StockMovement  $reversal  Exact inverse of the original movement.
     * @param  StockMovement  $replacement  Corrected replacement movement.
     */
    public function __construct(
        public StockMovement $original,
        public StockMovement $reversal,
        public StockMovement $replacement,
    ) {}
}
