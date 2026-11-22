<?php

namespace Modules\Reservation\Http\Requests\Api\V1;

use Modules\Core\Http\Requests\Request;

class ReverseReservationDepositRequest extends Request
{
    /**
     * Get validation rules for reversing a deposit ledger transaction.
     */
    public function rules(): array
    {
        return [
            'economic_date' => ['required', 'date', 'before_or_equal:today'],
            'idempotency_key' => ['required', 'string', 'max:255'],
            'reason' => ['required', 'string', 'max:10000'],
        ];
    }

    /**
     * Get the translation namespace for validation attributes.
     */
    protected function availableAttributes(): string
    {
        return 'reservation::attributes.reservation_deposit_transactions';
    }
}
