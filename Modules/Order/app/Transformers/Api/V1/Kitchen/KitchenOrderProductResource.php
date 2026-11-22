<?php

namespace Modules\Order\Transformers\Api\V1\Kitchen;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Modules\Order\Enums\OrderProductStatus;
use Modules\Order\Models\OrderProduct;

/** @mixin OrderProduct */
class KitchenOrderProductResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     */
    public function toArray(Request $request): array
    {
        $nextStatus = $this->status->nextStatus();
        $preparationStartedAt = $this->preparation_started_at
            ?? ($this->status === OrderProductStatus::Preparing ? $this->updated_at : null);
        $expectedReadyAt = $preparationStartedAt && $this->prep_time_minutes > 0
            ? $preparationStartedAt->copy()->addMinutes($this->prep_time_minutes)
            : null;

        if (
            $nextStatus === OrderProductStatus::Served
            && $this->relationLoaded('order')
            && $this->order?->relationLoaded('orderDelivery')
            && ! is_null($this->order->orderDelivery)
        ) {
            $nextStatus = null;
        }

        return [
            'id' => $this->id,
            'product' => [
                'id' => $this->product_id,
                ...($this->relationLoaded('product')
                    ? [
                        'name' => $this->product->name,
                    ]
                    : []),
            ],
            'status' => $this->status->toTrans(),
            'next_status' => $nextStatus?->toTrans(),
            'quantity' => $this->quantity,
            'prep_time_minutes' => $this->prep_time_minutes,
            'preparation_started_at' => $preparationStartedAt?->toISOString(),
            'prepared_at' => $this->prepared_at?->toISOString(),
            'expected_ready_at' => $expectedReadyAt?->toISOString(),
            'options' => KitchenOrderProductOptionResource::collection($this->whenLoaded('options')),
        ];
    }
}
