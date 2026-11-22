<?php

namespace Modules\Inventory\Observers;

use LogicException;
use Modules\Inventory\Models\StockTransferReceiptItem;

class StockTransferReceiptItemObserver
{
    /**
     * Protect immutable receipt values after creation.
     */
    public function updating(StockTransferReceiptItem $item): void
    {
        if (
            array_diff(array_keys($item->getDirty()), ['inbound_movement_id']) !== []
            || filled($item->getRawOriginal('inbound_movement_id'))
        ) {
            throw new LogicException(__('inventory::messages.stock_transfer_receipt_immutable'));
        }
    }

    /**
     * Prevent deleting immutable receipt lines.
     */
    public function deleting(): void
    {
        throw new LogicException(__('inventory::messages.stock_transfer_receipt_immutable'));
    }
}
