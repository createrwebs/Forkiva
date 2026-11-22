<?php

namespace Modules\Inventory\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use Modules\Inventory\Enums\StockTransferStatus;
use Modules\Inventory\Models\StockTransfer;

/**
 * Notify downstream workflows after a committed stock-transfer status change.
 */
class StockTransferStatusChanged
{
    use Dispatchable, SerializesModels;

    /**
     * Create a stock-transfer status event.
     */
    public function __construct(
        public StockTransfer $transfer,
        public StockTransferStatus $status,
        public ?string $reason = null,
    ) {}
}
