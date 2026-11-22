<?php

namespace Modules\Reservation\Transformers\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Modules\Reservation\Models\ReservationTable;

/** @mixin ReservationTable */
class ReservationTableAssignmentResource extends JsonResource
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
            'table' => [
                'id' => $this->table_id,
                'name' => $this->relationLoaded('table') ? $this->table?->name : null,
                'capacity' => $this->relationLoaded('table') ? $this->table?->capacity : null,
                'status' => $this->relationLoaded('table') ? $this->table?->status?->toTrans() : null,
            ],
            'assigned_by' => [
                'id' => $this->assigned_by,
                'name' => $this->relationLoaded('assignedBy') ? $this->assignedBy?->name : null,
            ],
            'assigned_at' => dateTimeFormat($this->assigned_at),
        ];
    }
}
