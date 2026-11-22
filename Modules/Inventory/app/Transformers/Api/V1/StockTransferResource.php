<?php

namespace Modules\Inventory\Transformers\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Modules\Inventory\Enums\StockTransferStatus;
use Modules\Inventory\Models\StockTransfer;
use Modules\Support\Enums\DateTimeFormat;

/** @mixin StockTransfer */
class StockTransferResource extends JsonResource
{
    /**
     * Transform a transfer for table and action consumers.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'reference_number' => $this->reference_number,
            'source_branch' => [
                'id' => $this->source_branch_id,
                'name' => $this->relationLoaded('sourceBranch') ? $this->sourceBranch?->name : null,
            ],
            'destination_branch' => [
                'id' => $this->destination_branch_id,
                'name' => $this->relationLoaded('destinationBranch') ? $this->destinationBranch?->name : null,
            ],
            'status' => $this->status->toTrans(),
            'currency' => $this->currency,
            'currency_rate' => $this->currency_rate,
            'expected_at' => dateTimeFormat($this->expected_at, DateTimeFormat::Date),
            'items_count' => $this->items_count ?? 0,
            'receipts_count' => $this->receipts_count ?? 0,
            ...$this->actions($request),
            'created_at' => dateTimeFormat($this->created_at),
            'updated_at' => dateTimeFormat($this->updated_at),
        ];
    }

    /**
     * Return server-authoritative action availability.
     *
     * @return array<string, bool>
     */
    protected function actions(Request $request): array
    {
        $user = $request->user();
        $assignedBranchId = $user?->assignedToBranch() ? (int) $user->branch_id : null;
        $isSource = $assignedBranchId === null || $assignedBranchId === $this->source_branch_id;
        $isDestination = $assignedBranchId === null || $assignedBranchId === $this->destination_branch_id;

        return [
            'allow_edit' => $isSource
                && $user?->can('admin.stock_transfers.edit')
                && $this->status === StockTransferStatus::Draft,
            'allow_approve' => $isSource
                && $user?->can('admin.stock_transfers.approve')
                && $this->status === StockTransferStatus::Draft,
            'allow_dispatch' => $isSource
                && $user?->can('admin.stock_transfers.dispatch')
                && $this->status === StockTransferStatus::Approved,
            'allow_receive' => $isDestination
                && $user?->can('admin.stock_transfers.receive')
                && in_array($this->status, [StockTransferStatus::Dispatched, StockTransferStatus::PartiallyReceived], true),
            'allow_close' => $isDestination
                && $user?->can('admin.stock_transfers.close')
                && in_array($this->status, [StockTransferStatus::Dispatched, StockTransferStatus::PartiallyReceived], true),
            'allow_cancel' => $isSource
                && $user?->can('admin.stock_transfers.cancel')
                && in_array($this->status, [StockTransferStatus::Draft, StockTransferStatus::Approved], true),
            'allow_void' => $isSource
                && $user?->can('admin.stock_transfers.void')
                && $this->status === StockTransferStatus::Dispatched
                && (int) ($this->receipts_count ?? 0) === 0,
        ];
    }
}
