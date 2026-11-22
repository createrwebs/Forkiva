<?php

namespace Modules\Inventory\Transformers\Api\V1;

use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;
use Illuminate\Http\Request;
use Modules\Core\Contracts\FinancialPostingStatusReaderInterface;
use Modules\Inventory\Models\StockTransfer;
use Modules\Support\Money;

/** @mixin StockTransfer */
class StockTransferShowResource extends StockTransferResource
{
    /**
     * Transform a transfer with lines, receipts, accounting, and audit details.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $items = $this->relationLoaded('items') ? $this->items : collect();
        $receipts = $this->relationLoaded('receipts') ? $this->receipts : collect();
        $postingStatus = app(FinancialPostingStatusReaderInterface::class);
        $postingLookups = collect([
            ['source_type' => 'stock_transfer', 'source_id' => $this->id, 'event' => 'dispatched'],
            ['source_type' => 'stock_transfer', 'source_id' => $this->id, 'event' => 'closed'],
            ['source_type' => 'stock_transfer', 'source_id' => $this->id, 'event' => 'voided'],
        ])->concat($receipts->map(fn ($receipt) => [
            'source_type' => 'stock_transfer_receipt',
            'source_id' => $receipt->id,
            'event' => 'received',
        ]))->all();
        $postingStatuses = $postingStatus->latestMany($postingLookups);

        return [
            ...parent::toArray($request),
            'uuid' => $this->uuid,
            'notes' => $this->notes,
            'items' => $items->map(function ($item): array {
                $remaining = BigDecimal::of((string) $item->dispatched_quantity)
                    ->minus(BigDecimal::of((string) $item->received_quantity))
                    ->minus(BigDecimal::of((string) $item->discrepancy_quantity))
                    ->toScale(6, RoundingMode::HalfUp);

                return [
                    'id' => $item->id,
                    'source_ingredient' => [
                        'id' => $item->source_ingredient_id,
                        'name' => $item->relationLoaded('sourceIngredient') ? $item->sourceIngredient?->name : null,
                    ],
                    'destination_ingredient' => [
                        'id' => $item->destination_ingredient_id,
                        'name' => $item->relationLoaded('destinationIngredient') ? $item->destinationIngredient?->name : null,
                    ],
                    'unit' => [
                        'id' => $item->unit_id,
                        'name' => $item->relationLoaded('unit') ? $item->unit?->name : null,
                        'symbol' => $item->relationLoaded('unit') ? $item->unit?->symbol : null,
                    ],
                    'requested_quantity' => $item->requested_quantity,
                    'dispatched_quantity' => $item->dispatched_quantity,
                    'received_quantity' => $item->received_quantity,
                    'discrepancy_quantity' => $item->discrepancy_quantity,
                    'remaining_quantity' => (string) $remaining,
                    'unit_cost' => $item->unit_cost,
                    'unit_cost_formatted' => $this->formatMoneyValue($item->unit_cost),
                    'total_cost' => $item->total_cost,
                    'total_cost_formatted' => $this->formatMoneyValue($item->total_cost),
                    'discrepancy_reason' => $item->discrepancy_reason,
                    'outbound_movement_id' => $item->outbound_movement_id,
                    'outbound_reversal_movement_id' => $item->outbound_reversal_movement_id,
                ];
            })->values(),
            'receipts' => $receipts->map(
                fn ($receipt) => (new StockTransferReceiptResource($receipt))->withAccountingStatus(
                    $postingStatuses["stock_transfer_receipt:$receipt->id:received"] ?? null,
                ),
            )->values(),
            'accounting' => [
                'dispatched' => $postingStatuses["stock_transfer:$this->id:dispatched"] ?? null,
                'closed' => $postingStatuses["stock_transfer:$this->id:closed"] ?? null,
                'voided' => $postingStatuses["stock_transfer:$this->id:voided"] ?? null,
            ],
            'audit' => [
                'created_by' => $this->relationLoaded('createdBy') ? $this->createdBy?->name : null,
                'approved_by' => $this->relationLoaded('approvedBy') ? $this->approvedBy?->name : null,
                'approved_at' => dateTimeFormat($this->approved_at),
                'dispatched_by' => $this->relationLoaded('dispatchedBy') ? $this->dispatchedBy?->name : null,
                'dispatched_at' => dateTimeFormat($this->dispatched_at),
                'completed_by' => $this->relationLoaded('completedBy') ? $this->completedBy?->name : null,
                'completed_at' => dateTimeFormat($this->completed_at),
                'cancelled_by' => $this->relationLoaded('cancelledBy') ? $this->cancelledBy?->name : null,
                'cancelled_at' => dateTimeFormat($this->cancelled_at),
                'cancellation_reason' => $this->cancellation_reason,
                'voided_by' => $this->relationLoaded('voidedBy') ? $this->voidedBy?->name : null,
                'voided_at' => dateTimeFormat($this->voided_at),
                'void_reason' => $this->void_reason,
            ],
        ];
    }

    /**
     * Format transfer money through the backend Money value object.
     */
    private function formatMoneyValue(mixed $amount): string
    {
        if ($amount === null || $amount === '') {
            return '-';
        }

        $currency = $this->currency
            ?: ($this->relationLoaded('sourceBranch') ? $this->sourceBranch?->currency : null)
                ?: setting('default_currency', 'JOD');

        return (new Money((float) $amount, $currency))->format();
    }
}
