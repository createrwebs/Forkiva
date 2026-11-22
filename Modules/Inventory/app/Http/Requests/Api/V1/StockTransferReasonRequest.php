<?php

namespace Modules\Inventory\Http\Requests\Api\V1;

use Modules\Core\Http\Requests\Request;
use Modules\Inventory\Models\StockTransfer;

/** @mixin StockTransfer */
class StockTransferReasonRequest extends Request
{
    /**
     * Get validation rules for cancellation, discrepancy, and void reasons.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return ['reason' => ['required', 'string', 'max:2000']];
    }

    /**
     * Get the translation namespace for validation attributes.
     */
    protected function availableAttributes(): string
    {
        return 'inventory::attributes.stock_transfers';
    }
}
