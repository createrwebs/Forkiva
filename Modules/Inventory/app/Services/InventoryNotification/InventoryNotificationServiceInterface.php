<?php

namespace Modules\Inventory\Services\InventoryNotification;

use Modules\Inventory\Enums\StockTransferStatus;
use Modules\Inventory\Models\Purchase;
use Modules\Inventory\Models\StockTransfer;

interface InventoryNotificationServiceInterface
{
    /**
     * Send the inventory-purchase updated notification.
     */
    public function sendPurchaseUpdated(Purchase $purchase): void;

    /**
     * Send the stock-movement attention notification when a purchase remains partially received.
     */
    public function sendStockMovementNeedsAttention(Purchase $purchase): void;

    /**
     * Notify both transfer branches and global administrators of a workflow transition.
     */
    public function sendStockTransferStatusChanged(
        StockTransfer $transfer,
        StockTransferStatus $status,
        ?string $reason = null,
    ): void;
}
