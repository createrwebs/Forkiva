<?php

namespace Modules\Inventory\Http\Requests\Api\V1;

use Modules\Core\Http\Requests\Request;

class CancelInventoryCountRequest extends Request
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return ['reason' => ['nullable', 'string', 'max:2000']];
    }

    /** {@inheritDoc} */
    protected function availableAttributes(): string
    {
        return 'inventory::attributes.inventory_counts';
    }
}
