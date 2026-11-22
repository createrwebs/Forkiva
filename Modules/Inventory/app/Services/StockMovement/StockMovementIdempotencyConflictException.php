<?php

namespace Modules\Inventory\Services\StockMovement;

use RuntimeException;

/**
 * Raised when an idempotency key is reused for different stock movement data.
 */
final class StockMovementIdempotencyConflictException extends RuntimeException
{
    /**
     * Create a stock movement idempotency conflict exception.
     */
    public function __construct()
    {
        parent::__construct(__('inventory::messages.stock_movement_idempotency_conflict'));
    }
}
