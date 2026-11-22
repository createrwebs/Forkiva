<?php

namespace Modules\Reservation\Transformers\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Modules\Reservation\Models\ReservationStatusLog;

/** @mixin ReservationStatusLog */
class ReservationStatusLogResource extends JsonResource
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
            'old_status' => $this->old_status?->toTrans(),
            'new_status' => $this->new_status?->toTrans(),
            'reason' => $this->reason,
            'note' => $this->note,
            'changed_by' => [
                'id' => $this->changed_by,
                'name' => $this->relationLoaded('changedBy') ? $this->changedBy?->name : null,
            ],
            'changed_at' => dateTimeFormat($this->changed_at),
        ];
    }
}
