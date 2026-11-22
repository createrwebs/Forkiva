<?php

namespace Modules\Reservation\Transformers\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Modules\Reservation\Models\ReservationReminder;

/** @mixin ReservationReminder */
class ReservationReminderResource extends JsonResource
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
            'status' => $this->status?->toTrans(),
            'scheduled_at' => dateTimeFormat($this->scheduled_at),
            'sent_at' => dateTimeFormat($this->sent_at),
            'failed_at' => dateTimeFormat($this->failed_at),
            'error_message' => $this->error_message,
        ];
    }
}
