<?php

namespace Modules\Reservation\Transformers\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Modules\Reservation\Models\ReservationWaitlist;
use Modules\Support\Enums\DateTimeFormat;

/** @mixin ReservationWaitlist */
class ReservationWaitlistResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'branch' => [
                'id' => $this->branch_id,
                'name' => $this->relationLoaded('branch') ? $this->branch?->name : null,
            ],
            'customer' => [
                'id' => $this->customer_id,
                'name' => $this->relationLoaded('customer') ? $this->customer?->name : null,
                'phone_country_iso_code' => $this->relationLoaded('customer') ? $this->customer?->phone_country_iso_code : null,
            ],
            'status' => $this->status?->toTrans(),
            'phone_country_iso_code' => $this->phone_country_iso_code,
            'customer_name' => $this->customer_name,
            'customer_phone' => $this->customer_phone,
            'customer_national_phone' => $this->customer_national_phone,
            'customer_email' => $this->customer_email,
            'guest_count' => $this->guest_count,
            'preferred_date' => dateTimeFormat($this->preferred_date, DateTimeFormat::Date),
            'preferred_start_time' => dateTimeFormat($this->preferred_start_time, DateTimeFormat::Time),
            'preferred_end_time' => dateTimeFormat($this->preferred_end_time, DateTimeFormat::Time),
            'estimated_wait_minutes' => $this->estimated_wait_minutes,
            'priority' => $this->priority,
            'occasion' => $this->occasion,
            'special_requests' => $this->special_requests,
            'internal_notes' => $this->internal_notes,
            'reservation' => [
                'id' => $this->converted_reservation_id,
                'number' => $this->relationLoaded('convertedReservation') ? $this->convertedReservation?->reservation_number : null,
            ],
            'notified_at' => dateTimeFormat($this->notified_at),
            'converted_at' => dateTimeFormat($this->converted_at),
            'cancelled_at' => dateTimeFormat($this->cancelled_at),
            'expires_at' => dateTimeFormat($this->expires_at),
            'expired_at' => dateTimeFormat($this->expired_at),
            'created_by' => [
                'id' => $this->created_by,
                'name' => $this->relationLoaded('createdBy') ? $this->createdBy?->name : null,
            ],
            'created_at' => dateTimeFormat($this->created_at),
            'updated_at' => dateTimeFormat($this->updated_at),
        ];
    }
}
