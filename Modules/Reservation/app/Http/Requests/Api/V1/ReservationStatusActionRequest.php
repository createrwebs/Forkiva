<?php

namespace Modules\Reservation\Http\Requests\Api\V1;

use Illuminate\Validation\Rule;
use Modules\Core\Http\Requests\Request;
use Modules\Reservation\Enums\ReservationStatus;

class ReservationStatusActionRequest extends Request
{
    /**
     * Get validation rules for status actions.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'status' => ['required', Rule::enum(ReservationStatus::class)],
            'reason' => ['nullable', 'string', 'max:1000'],
            'note' => ['nullable', 'string', 'max:1000'],
        ];
    }

    /** {@inheritDoc} */
    protected function availableAttributes(): string
    {
        return 'reservation::attributes.reservation_status_logs';
    }
}
