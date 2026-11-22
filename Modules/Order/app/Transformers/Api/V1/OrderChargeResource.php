<?php

namespace Modules\Order\Transformers\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Modules\Order\Models\OrderCharge;

/** @mixin OrderCharge */
class OrderChargeResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'code' => $this->code,
            'name' => $this->name,
            'quantity' => $this->quantity,
            'unit_amount' => $this->unit_amount->withConvertedDefaultCurrency($this->currency_rate),
            'amount' => $this->amount->withConvertedDefaultCurrency($this->currency_rate),
            'meta' => $this->meta,
        ];
    }
}
