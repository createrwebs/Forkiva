<?php

namespace Modules\Reservation\Http\Requests\Api\V1;

use Modules\Core\Http\Requests\Request;

class CancelReservationWaitlistRequest extends Request
{
    /**
     * Get validation rules for waitlist cancellation.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'reason' => ['nullable', 'string', 'max:1000'],
        ];
    }

    /** {@inheritDoc} */
    protected function availableAttributes(): string
    {
        return 'reservation::attributes.reservation_waitlists';
    }
}
