<?php

namespace Modules\Inventory\Http\Requests\Api\V1;

use Modules\Core\Http\Requests\Request;

class StockTransferIngredientOptionsRequest extends Request
{
    /**
     * Get validation rules for searchable stock-transfer ingredient options.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'branch_id' => ['required', 'integer', 'exists:branches,id,deleted_at,NULL'],
            'role' => ['required', 'in:source,destination'],
            'search' => ['nullable', 'string', 'max:255'],
            'ids' => ['nullable', 'array', 'max:100'],
            'ids.*' => ['integer', 'distinct', 'exists:ingredients,id,deleted_at,NULL'],
            'unit_id' => ['nullable', 'integer', 'exists:units,id,deleted_at,NULL'],
            'source_branch_id' => ['nullable', 'integer', 'exists:branches,id,deleted_at,NULL'],
        ];
    }

    /** {@inheritDoc} */
    protected function availableAttributes(): string
    {
        return 'inventory::attributes.stock_transfers';
    }
}
