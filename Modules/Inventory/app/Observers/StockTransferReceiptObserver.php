<?php

namespace Modules\Inventory\Observers;

use Illuminate\Support\Str;
use LogicException;
use Modules\Inventory\Models\StockTransferReceipt;

class StockTransferReceiptObserver
{
    /**
     * Assign a UUID before persisting the receipt.
     */
    public function creating(StockTransferReceipt $receipt): void
    {
        $receipt->uuid ??= (string) Str::uuid();
    }

    /**
     * Freeze committed receipt history.
     */
    public function updating(StockTransferReceipt $receipt): void
    {
        if (
            array_diff(array_keys($receipt->getDirty()), ['reference_number']) !== []
            || filled($receipt->getRawOriginal('reference_number'))
        ) {
            throw new LogicException(__('inventory::messages.stock_transfer_receipt_immutable'));
        }
    }

    /**
     * Allow soft deletion but prevent permanent deletion.
     */
    public function deleting(StockTransferReceipt $receipt): void
    {
        if ($receipt->isForceDeleting()) {
            throw new LogicException(__('inventory::messages.stock_transfer_receipt_immutable'));
        }
    }
}
