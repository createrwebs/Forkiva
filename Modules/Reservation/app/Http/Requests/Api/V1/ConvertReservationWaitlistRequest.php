<?php

namespace Modules\Reservation\Http\Requests\Api\V1;

use Modules\Core\Http\Requests\Request;
use Modules\Reservation\Support\TimeInput;

class ConvertReservationWaitlistRequest extends Request
{
    /**
     * Normalize time inputs before validation.
     */
    protected function prepareForValidation(): void
    {
        $this->merge([
            'start_time' => TimeInput::normalize($this->input('start_time')),
        ]);
    }

    /**
     * Get validation rules for waitlist reservation conversion.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'reservation_date' => ['nullable', 'date_format:Y-m-d'],
            'start_time' => ['required', 'date_format:H:i'],
            'duration_minutes' => ['nullable', 'integer', 'min:1', 'max:1440'],
            'table_ids' => ['nullable', 'array'],
            'table_ids.*' => ['integer', 'distinct', 'exists:tables,id,deleted_at,NULL'],
        ];
    }

    /** {@inheritDoc} */
    protected function availableAttributes(): string
    {
        return 'reservation::attributes.reservations';
    }
}
