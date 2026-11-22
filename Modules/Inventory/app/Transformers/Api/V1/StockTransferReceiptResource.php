<?php

namespace Modules\Inventory\Transformers\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Modules\Core\Contracts\FinancialPostingStatusReaderInterface;
use Modules\Inventory\Models\StockTransferReceipt;
use Modules\Support\Money;

/** @mixin StockTransferReceipt */
class StockTransferReceiptResource extends JsonResource
{
    private bool $accountingStatusResolved = false;

    /** @var array{id: string, name: string, color: string|null, journal_entry_id: int|null}|null */
    private ?array $resolvedAccountingStatus = null;

    /**
     * Supply a status resolved by the parent transfer resource.
     */
    public function withAccountingStatus(?array $status): static
    {
        $this->accountingStatusResolved = true;
        $this->resolvedAccountingStatus = $status;

        return $this;
    }

    /**
     * Transform an immutable transfer receipt and its movement allocations.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $items = $this->relationLoaded('items') ? $this->items : collect();

        return [
            'id' => $this->id,
            'reference_number' => $this->reference_number,
            'idempotency_key' => $this->idempotency_key,
            'notes' => $this->notes,
            'received_by' => $this->relationLoaded('receivedBy') ? $this->receivedBy?->name : null,
            'received_at' => dateTimeFormat($this->received_at),
            'deleted_at' => dateTimeFormat($this->deleted_at),
            'accounting_status' => $this->accountingStatusResolved
                ? $this->resolvedAccountingStatus
                : app(FinancialPostingStatusReaderInterface::class)
                    ->latest('stock_transfer_receipt', $this->id, 'received'),
            'items' => $items->map(fn ($item) => [
                'id' => $item->id,
                'stock_transfer_item_id' => $item->stock_transfer_item_id,
                'quantity' => $item->quantity,
                'unit_cost' => $item->unit_cost,
                'unit_cost_formatted' => $this->formatMoneyValue($item->unit_cost),
                'total_cost' => $item->total_cost,
                'total_cost_formatted' => $this->formatMoneyValue($item->total_cost),
                'inbound_movement_id' => $item->inbound_movement_id,
            ])->values(),
        ];
    }

    /**
     * Format receipt money through the backend Money value object.
     */
    private function formatMoneyValue(mixed $amount): string
    {
        if ($amount === null || $amount === '') {
            return '-';
        }

        $transfer = $this->transfer;
        $currency = $transfer?->currency
            ?: $transfer?->sourceBranch?->currency
            ?: setting('default_currency', 'JOD');

        return (new Money((float) $amount, $currency))->format();
    }
}
