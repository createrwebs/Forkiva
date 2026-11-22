<?php

namespace Modules\Pos\Transformers\Api\V1\Pos;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Modules\Order\Support\PricingCatalog;
use Modules\Product\Models\Product;

/** @mixin Product */
class PosProductResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     */
    public function toArray(Request $request): array
    {
        return [
            'pricing_taxes' => PricingCatalog::taxes($this->resource->taxes),
            'id' => $this->id,
            'name' => $this->name,
            'thumbnail' => $this->thumbnail?->preview_image_url,
            'price' => $this->price,
            'selling_price' => $this->selling_price,
            'has_special_price' => $this->hasSpecialPrice(),
            'is_new' => $this->isNew(),
            'category_ids' => $this->relationLoaded('categories') ? $this->categories->pluck('id') : [],
            'options' => $this->relationLoaded('options')
                ? PosProductOptionResource::collection($this->options)
                : [],
        ];
    }
}
