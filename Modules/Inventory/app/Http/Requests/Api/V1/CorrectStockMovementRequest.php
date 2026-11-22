<?php

namespace Modules\Inventory\Http\Requests\Api\V1;

use Modules\Core\Http\Requests\Request;
use Modules\Inventory\Models\StockMovement;

/** @mixin StockMovement */
class CorrectStockMovementRequest extends Request
{
    /**
     * Get the validation rules that apply to the correction request.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'quantity' => ['required', 'numeric', 'gt:0'],
            'reason' => ['required', 'string', 'max:1000'],
        ];
    }

    /** {@inheritDoc} */
    protected function availableAttributes(): string
    {
        return 'inventory::attributes.stock_movements';
    }
}
