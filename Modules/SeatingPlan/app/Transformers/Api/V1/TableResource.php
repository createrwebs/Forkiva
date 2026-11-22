<?php

namespace Modules\SeatingPlan\Transformers\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Modules\SeatingPlan\Models\Table;

/** @mixin Table */
class TableResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'uuid' => $this->uuid,
            'qrcode' => $this->uuid,
            'table_qr_link' => filled($this->table_qr_token)
                ? "/table-order/$this->uuid?token=$this->table_qr_token"
                : null,
            'table_qr_token_masked' => filled($this->table_qr_token) ? str_repeat('*', 16) : null,
            'table_qr_token_regenerated_at' => dateTimeFormat($this->table_qr_token_regenerated_at),
            'table_qr_status' => [
                'id' => $this->is_active ? 'active' : 'inactive',
                'name' => $this->is_active
                    ? __('seatingplan::tables.qr_status.active')
                    : __('seatingplan::tables.qr_status.inactive'),
            ],
            'name' => $this->name,
            'branch' => [
                'id' => $this->branch_id,
                'name' => $this->relationLoaded('branch') ? $this->branch?->name : '',
            ],
            'floor' => [
                'id' => $this->floor_id,
                'name' => $this->relationLoaded('floor') ? $this->floor?->name : '',
            ],
            'zone' => [
                'id' => $this->zone_id,
                'name' => $this->relationLoaded('zone') ? $this->zone?->name : '',
            ],
            'capacity' => $this->capacity,
            'status' => $this->status?->toTrans(),
            'shape' => $this->shape->toTrans(),
            'is_active' => $this->is_active,
            'updated_at' => dateTimeFormat($this->updated_at),
            'created_at' => dateTimeFormat($this->created_at),
        ];
    }
}
