<?php

namespace Modules\Inventory\Transformers\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Modules\Inventory\Enums\InventoryCountStatus;
use Modules\Inventory\Models\InventoryCount;
use Modules\Support\Enums\DateTimeFormat;

/** @mixin InventoryCount */
class InventoryCountShowResource extends JsonResource
{
    /**
     * Transform the resource into a detailed audit payload.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $user = $request->user();
        $items = $this->relationLoaded('items') ? $this->items : collect();

        return [
            'id' => $this->id,
            'reference_number' => $this->reference_number,
            'branch' => [
                'id' => $this->branch_id,
                'name' => $this->relationLoaded('branch') ? $this->branch?->name : null,
                'currency' => $this->relationLoaded('branch') ? $this->branch?->currency : null,
            ],
            'count_date' => dateTimeFormat($this->count_date, DateTimeFormat::Date),
            'status' => $this->status->toTrans(),
            'notes' => $this->notes,
            'allow_edit' => $user?->can('admin.inventory_counts.edit') && $this->status === InventoryCountStatus::Draft,
            'allow_approve' => $user?->can('admin.inventory_counts.approve') && $this->status === InventoryCountStatus::Draft,
            'allow_cancel' => $user?->can('admin.inventory_counts.cancel') && $this->status === InventoryCountStatus::Draft,
            'items' => $items->map(fn ($item) => [
                'id' => $item->id,
                'ingredient' => [
                    'id' => $item->ingredient_id,
                    'name' => $item->relationLoaded('ingredient') ? $item->ingredient?->name : null,
                ],
                'unit' => [
                    'id' => $item->unit_id,
                    'name' => $item->relationLoaded('unit') ? $item->unit?->name : null,
                    'symbol' => $item->relationLoaded('unit') ? $item->unit?->symbol : null,
                ],
                'expected_quantity' => $item->expected_quantity,
                'counted_quantity' => $item->counted_quantity,
                'variance_quantity' => $item->variance_quantity,
                'snapshot_movement_id' => $item->snapshot_movement_id,
                'generated_movement' => $item->relationLoaded('generatedMovement') && $item->generatedMovement ? [
                    'id' => $item->generatedMovement->id,
                    'type' => $item->generatedMovement->type->toTrans(),
                    'quantity' => $item->generatedMovement->quantity,
                ] : null,
            ])->values(),
            'created_by' => $this->relationLoaded('createdBy') ? $this->createdBy?->name : null,
            'approved_by' => $this->relationLoaded('approvedBy') ? $this->approvedBy?->name : null,
            'approved_at' => dateTimeFormat($this->approved_at),
            'cancelled_by' => $this->relationLoaded('cancelledBy') ? $this->cancelledBy?->name : null,
            'cancelled_at' => dateTimeFormat($this->cancelled_at),
            'cancellation_reason' => $this->cancellation_reason,
            'created_at' => dateTimeFormat($this->created_at),
            'updated_at' => dateTimeFormat($this->updated_at),
        ];
    }
}
