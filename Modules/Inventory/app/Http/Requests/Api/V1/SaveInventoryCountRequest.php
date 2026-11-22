<?php

namespace Modules\Inventory\Http\Requests\Api\V1;

use Modules\Core\Http\Requests\Request;
use Modules\Inventory\Models\InventoryCount;

/** @mixin InventoryCount */
class SaveInventoryCountRequest extends Request
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            ...$this->getBranchRule(),
            'count_date' => ['required', 'date_format:Y-m-d'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.ingredient_id' => [
                'required',
                'integer',
                'distinct',
                "exists:ingredients,id,branch_id,$this->branch_id,deleted_at,NULL",
            ],
            'items.*.counted_quantity' => ['required', 'numeric', 'min:0', 'max:999999999.9999'],
        ];
    }

    /** {@inheritDoc} */
    protected function availableAttributes(): string
    {
        return 'inventory::attributes.inventory_counts';
    }
}
