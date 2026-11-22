<?php

namespace Modules\Inventory\Http\Requests\Api\V1;

use Modules\Core\Http\Requests\Request;
use Modules\Inventory\Models\StockTransfer;

/** @mixin StockTransfer */
class SaveStockTransferRequest extends Request
{
    /**
     * Get the validation rules for a draft transfer definition.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'source_branch_id' => ['required', 'integer', 'exists:branches,id,deleted_at,NULL'],
            'destination_branch_id' => ['required', 'integer', 'different:source_branch_id', 'exists:branches,id,deleted_at,NULL'],
            'expected_at' => ['nullable', 'date'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.source_ingredient_id' => ['required', 'integer', 'distinct', 'exists:ingredients,id,deleted_at,NULL'],
            'items.*.destination_ingredient_id' => ['required', 'integer', 'distinct', 'exists:ingredients,id,deleted_at,NULL'],
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
        return 'inventory::attributes.stock_transfers';
    }
}
