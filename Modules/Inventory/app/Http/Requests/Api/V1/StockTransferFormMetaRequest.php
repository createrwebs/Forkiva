<?php

namespace Modules\Inventory\Http\Requests\Api\V1;

use Modules\Core\Http\Requests\Request;

class StockTransferFormMetaRequest extends Request
{
    /**
     * Get validation rules for stock-transfer form metadata.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'source_branch_id' => ['nullable', 'integer', 'exists:branches,id,deleted_at,NULL'],
            'destination_branch_id' => ['nullable', 'integer', 'different:source_branch_id', 'exists:branches,id,deleted_at,NULL'],
            'source_ingredient_ids' => ['nullable', 'array', 'max:100'],
            'source_ingredient_ids.*' => ['integer', 'distinct', 'exists:ingredients,id,deleted_at,NULL'],
            'destination_ingredient_ids' => ['nullable', 'array', 'max:100'],
            'destination_ingredient_ids.*' => ['integer', 'distinct', 'exists:ingredients,id,deleted_at,NULL'],
        ];
    }

    /** {@inheritDoc} */
    protected function availableAttributes(): string
    {
        return 'inventory::attributes.stock_transfers';
    }
}
