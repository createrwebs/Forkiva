<?php

namespace Modules\SeatingPlan\Http\Requests\Api\V1;

use Modules\Core\Http\Requests\Request;
use Modules\Order\Support\CheckoutInput;

class SubmitTableQrOrderRequest extends Request
{
    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        return [
            ...CheckoutInput::rules(public: true),
            'guest_count' => ['nullable', 'integer', 'min:1', 'max:50'],
            'customer_note' => ['nullable', 'string', 'max:500'],
        ];
    }

    /** {@inheritDoc} */
    protected function availableAttributes(): string
    {
        return 'seatingplan::attributes.table_qr';
    }
}
