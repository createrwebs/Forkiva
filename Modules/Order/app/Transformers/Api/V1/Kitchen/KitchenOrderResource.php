<?php

namespace Modules\Order\Transformers\Api\V1\Kitchen;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Modules\Order\Enums\OrderProductStatus;
use Modules\Order\Enums\OrderType;
use Modules\Order\Models\Order;
use Modules\Support\Enums\DateTimeFormat;

/** @mixin Order */
class KitchenOrderResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     */
    public function toArray(Request $request): array
    {
        $timedProducts = $this->relationLoaded('products')
            ? $this->products->filter(fn ($product) => $product->prep_time_minutes > 0
                && ! in_array($product->status, [OrderProductStatus::Cancelled, OrderProductStatus::Refunded], true))
            : collect();
        $productStartTimes = $timedProducts
            ->map(fn ($product) => $product->preparation_started_at
                ?? ($product->status === OrderProductStatus::Preparing ? $product->updated_at : null))
            ->filter();
        $preparationStartedAt = $this->preparation_started_at ?? $productStartTimes->sort()->first();
        $expectedReadyAt = $timedProducts
            ->map(function ($product) {
                $startedAt = $product->preparation_started_at
                    ?? ($product->status === OrderProductStatus::Preparing ? $product->updated_at : null);

                return $startedAt?->copy()->addMinutes($product->prep_time_minutes);
            })
            ->filter()
            ->sortDesc()
            ->first();

        return [
            'id' => $this->id,
            'reference_no' => $this->reference_no,
            'created_at' => $this->created_at?->toISOString(),
            'notes' => $this->notes,
            'customer' => $this->relationLoaded('customer') && $this->customer
                ? ['name' => $this->customer->name]
                : null,
            'order_number' => $this->order_number,
            'status' => $this->status->toTrans(),
            'next_status' => $this->next_status?->toTrans(),
            'type' => $this->type->toTrans(),
            'source' => $this->source?->toTrans(),
            'source_badge' => $this->source?->toTrans(),
            'payment_status' => $this->payment_status->toTrans(),
            'table' => $this->type === OrderType::DineIn
                ? [
                    'id' => $this->table_id,
                    'name' => $this->relationLoaded('table') ? $this->table?->name : null,
                ]
                : null,
            'products' => KitchenOrderProductResource::collection($this->whenLoaded('products')),
            'prep_time_minutes' => (int) ($timedProducts->max('prep_time_minutes') ?? 0),
            'preparation_started_at' => $preparationStartedAt?->toISOString(),
            'prepared_at' => $this->prepared_at?->toISOString(),
            'expected_ready_at' => $expectedReadyAt?->toISOString(),
            'time' => dateTimeFormat($this->created_at, DateTimeFormat::Time),
            'date' => dateTimeFormat($this->created_at, DateTimeFormat::Date),
        ];
    }
}
