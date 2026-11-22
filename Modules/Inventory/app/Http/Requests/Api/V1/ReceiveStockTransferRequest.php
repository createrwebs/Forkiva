<?php

namespace Modules\Inventory\Http\Requests\Api\V1;

use Modules\Core\Http\Requests\Request;
use Modules\Inventory\Models\StockTransferReceipt;

/** @mixin StockTransferReceipt */
class ReceiveStockTransferRequest extends Request
{
    /**
     * Get validation rules for an idempotent stock-transfer receipt.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'idempotency_key' => ['required', 'uuid'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.stock_transfer_item_id' => ['required', 'integer', 'distinct'],
            'items.*.quantity' => [
                'required',
                'numeric',
                'decimal:0,6',
                'min:0.000001',
                'max:999999999999.999999',
            ],
        ];
    }

    /**
     * Get the translation namespace for validation attributes.
     */
    protected function availableAttributes(): string
    {
        return 'inventory::attributes.stock_transfer_receipts';
    }
}
