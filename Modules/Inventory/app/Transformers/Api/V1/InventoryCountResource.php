<?php

namespace Modules\Inventory\Transformers\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Modules\Inventory\Enums\InventoryCountStatus;
use Modules\Inventory\Models\InventoryCount;
use Modules\Support\Enums\DateTimeFormat;

/** @mixin InventoryCount */
class InventoryCountResource extends JsonResource
{
    /**
     * Transform the resource into an index payload.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $user = $request->user();

        return [
            'id' => $this->id,
            'reference_number' => $this->reference_number,
            'branch' => [
                'id' => $this->branch_id,
                'name' => $this->relationLoaded('branch') ? $this->branch?->name : null,
            ],
            'count_date' => dateTimeFormat($this->count_date, DateTimeFormat::Date),
            'status' => $this->status->toTrans(),
            'items_count' => $this->items_count ?? 0,
            'allow_edit' => $user?->can('admin.inventory_counts.edit') && $this->status === InventoryCountStatus::Draft,
            'allow_approve' => $user?->can('admin.inventory_counts.approve') && $this->status === InventoryCountStatus::Draft,
            'allow_cancel' => $user?->can('admin.inventory_counts.cancel') && $this->status === InventoryCountStatus::Draft,
            'created_at' => dateTimeFormat($this->created_at),
            'updated_at' => dateTimeFormat($this->updated_at),
        ];
    }
}
