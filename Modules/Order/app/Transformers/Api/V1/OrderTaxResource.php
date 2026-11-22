<?php

namespace Modules\Order\Transformers\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Modules\Order\Models\OrderTax;

/** @mixin OrderTax */
class OrderTaxResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     */
    public function toArray(Request $request): array
    {
        $scope = is_null($this->order_product_id) ? 'global' : 'product';

        return [
            'id' => $this->id,
            'key' => $scope.':'.($this->tax_id ?? $this->id),
            'tax_id' => $this->tax_id,
            'name' => $this->name,
            'rate' => $this->rate,
            'type' => $this->type,
            'scope' => $scope,
            'amount' => $this->amount->withConvertedDefaultCurrency($this->currency_rate),
            'compound' => $this->compound,
        ];
    }
}
