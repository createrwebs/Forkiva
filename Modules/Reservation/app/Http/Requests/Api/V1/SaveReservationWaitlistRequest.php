<?php

namespace Modules\Reservation\Http\Requests\Api\V1;

use Illuminate\Validation\Rule;
use Modules\Core\Http\Requests\Request;
use Modules\Reservation\Support\TimeInput;
use Modules\Support\Country;

class SaveReservationWaitlistRequest extends Request
{
    /**
     * Get validation rules for waitlist records.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            ...$this->getBranchRule(),
            'customer_id' => ['nullable', 'integer', Rule::exists('users', 'id')->whereNull('deleted_at')],
            'customer_name' => ['required', 'string', 'min:2', 'max:255'],
            'phone_country_iso_code' => ['required', 'string', Rule::in(Country::codes())],
            'customer_phone' => ['bail', 'required', 'phone:phone_country_iso_code'],
            'customer_email' => ['nullable', 'email', 'max:255'],
            'guest_count' => ['required', 'integer', 'min:1'],
            'estimated_wait_minutes' => ['nullable', 'integer', 'min:0'],
            'preferred_date' => ['required', 'date_format:Y-m-d'],
            'preferred_start_time' => ['nullable', 'date_format:H:i'],
            'preferred_end_time' => ['nullable', 'date_format:H:i'],
            'occasion' => ['nullable', 'string', 'max:255'],
            'special_requests' => ['nullable', 'string', 'max:2000'],
            'internal_notes' => ['nullable', 'string', 'max:2000'],
            'expires_at' => ['nullable', 'date'],
        ];
    }

    /**
     * Normalize time inputs before validation.
     */
    protected function prepareForValidation(): void
    {
        $this->merge([
            'preferred_start_time' => TimeInput::normalize($this->input('preferred_start_time')),
            'preferred_end_time' => TimeInput::normalize($this->input('preferred_end_time')),
        ]);
    }

    /** {@inheritDoc} */
    protected function availableAttributes(): string
    {
        return 'reservation::attributes.reservation_waitlists';
    }
}
