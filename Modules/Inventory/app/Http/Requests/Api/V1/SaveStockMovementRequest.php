<?php

namespace Modules\Inventory\Http\Requests\Api\V1;

use Illuminate\Validation\Rule;
use Modules\Core\Http\Requests\Request;
use Modules\Inventory\Enums\StockMovementType;
use Modules\Inventory\Models\StockMovement;

/** @mixin StockMovement */
class SaveStockMovementRequest extends Request
{
    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        return [
            ...$this->getBranchRule(),
            'ingredient_id' => 'bail|required|numeric|exists:ingredients,id,deleted_at,NULL',
            'type' => [
                'required',
                Rule::in([
                    StockMovementType::AdjustAdd->value,
                    StockMovementType::AdjustSubtract->value,
                    StockMovementType::Waste->value,
                    StockMovementType::Spoil->value,
                    StockMovementType::Sample->value,
                    StockMovementType::StaffMeal->value,
                ]),
            ],
            'quantity' => 'required|numeric|min:0.01',
            'note' => [
                Rule::requiredIf(fn (): bool => in_array($this->input('type'), [
                    StockMovementType::Waste->value,
                    StockMovementType::Spoil->value,
                    StockMovementType::Sample->value,
                    StockMovementType::StaffMeal->value,
                ], true)),
                'nullable',
                'string',
                'max:1000',
            ],
            'idempotency_key' => ['required', 'string', 'max:191'],
        ];
    }

    /** {@inheritDoc} */
    protected function availableAttributes(): string
    {
        return 'inventory::attributes.stock_movements';
    }
}
