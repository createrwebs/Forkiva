<?php

namespace Modules\Inventory\Observers;

use LogicException;
use Modules\Inventory\Enums\StockTransferStatus;
use Modules\Inventory\Models\StockTransfer;
use Modules\Inventory\Models\StockTransferItem;

class StockTransferItemObserver
{
    /**
     * Prevent adding lines to non-draft transfers.
     */
    public function creating(StockTransferItem $item): void
    {
        $transfer = StockTransfer::query()
            ->withoutGlobalScope('stock_transfer_branch_permission')
            ->find($item->stock_transfer_id);

        if ($transfer && $transfer->status !== StockTransferStatus::Draft) {
            throw new LogicException(__('inventory::messages.stock_transfer_document_frozen'));
        }
    }

    /**
     * Freeze document-definition fields after the transfer leaves draft.
     */
    public function updating(StockTransferItem $item): void
    {
        $this->guardDocumentDefinition($item);
        $this->guardOperationalSnapshots($item);
    }

    /**
     * Prevent deleting lines from non-draft transfers.
     */
    public function deleting(StockTransferItem $item): void
    {
        $transfer = StockTransfer::query()
            ->withoutGlobalScope('stock_transfer_branch_permission')
            ->find($item->stock_transfer_id);

        if ($transfer && $transfer->status !== StockTransferStatus::Draft) {
            throw new LogicException(__('inventory::messages.stock_transfer_document_frozen'));
        }
    }

    private function guardDocumentDefinition(StockTransferItem $item): void
    {
        if (! $item->isDirty([
            'source_ingredient_id',
            'destination_ingredient_id',
            'unit_id',
            'requested_quantity',
        ])) {
            return;
        }

        $transfer = StockTransfer::query()
            ->withoutGlobalScope('stock_transfer_branch_permission')
            ->findOrFail($item->stock_transfer_id);

        if ($transfer->status !== StockTransferStatus::Draft) {
            throw new LogicException(__('inventory::messages.stock_transfer_document_frozen'));
        }
    }

    private function guardOperationalSnapshots(StockTransferItem $item): void
    {
        foreach ([
            'unit_cost',
            'total_cost',
            'outbound_movement_id',
            'outbound_reversal_movement_id',
            'discrepancy_reason',
        ] as $snapshotField) {
            if ($item->isDirty($snapshotField) && filled($item->getRawOriginal($snapshotField))) {
                throw new LogicException(__('inventory::messages.stock_transfer_document_frozen'));
            }
        }

        if (
            $item->isDirty('dispatched_quantity')
            && (float) $item->getRawOriginal('dispatched_quantity') !== 0.0
        ) {
            throw new LogicException(__('inventory::messages.stock_transfer_document_frozen'));
        }

        if (
            $item->isDirty('received_quantity')
            && (float) $item->received_quantity < (float) $item->getRawOriginal('received_quantity')
        ) {
            throw new LogicException(__('inventory::messages.stock_transfer_document_frozen'));
        }

        if (
            $item->isDirty('discrepancy_quantity')
            && (float) $item->getRawOriginal('discrepancy_quantity') !== 0.0
        ) {
            throw new LogicException(__('inventory::messages.stock_transfer_document_frozen'));
        }
    }
}
