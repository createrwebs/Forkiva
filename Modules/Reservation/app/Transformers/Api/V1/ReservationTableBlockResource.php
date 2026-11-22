<?php

namespace Modules\Reservation\Transformers\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Modules\Reservation\Models\ReservationTableBlock;

/** @mixin ReservationTableBlock */
class ReservationTableBlockResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $table = $this->relationLoaded('table') ? $this->table : null;
        $floor = $table?->relationLoaded('floor') ? $table->floor : null;
        $zone = $table?->relationLoaded('zone') ? $table->zone : null;

        return [
            'id' => $this->id,
            'branch' => [
                'id' => $this->branch_id,
                'name' => $this->relationLoaded('branch') ? $this->branch?->name : null,
            ],
            'table' => [
                'id' => $this->table_id,
                'name' => $table?->name,
                'capacity' => $table?->capacity,
                'floor' => [
                    'id' => $floor?->id,
                    'name' => $floor?->name,
                ],
                'zone' => [
                    'id' => $zone?->id,
                    'name' => $zone?->name,
                ],
            ],
            'title' => $this->title,
            'reason' => $this->reason,
            'start_at' => dateTimeFormat($this->start_at),
            'end_at' => dateTimeFormat($this->end_at),
            'created_by' => [
                'id' => $this->created_by,
                'name' => $this->relationLoaded('createdBy') ? $this->createdBy?->name : null,
            ],
            'created_at' => dateTimeFormat($this->created_at),
            'updated_at' => dateTimeFormat($this->updated_at),
        ];
    }
}
