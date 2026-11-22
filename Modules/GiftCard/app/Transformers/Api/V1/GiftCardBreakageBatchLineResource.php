<?php

namespace Modules\GiftCard\Transformers\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Modules\GiftCard\Models\GiftCardBreakageBatchLine;
use Modules\Support\Enums\DateTimeFormat;

/** @mixin GiftCardBreakageBatchLine */
class GiftCardBreakageBatchLineResource extends JsonResource
{
    /**
     * Transform the frozen Gift Card breakage line into its API shape.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'gift_card' => [
                'id' => $this->gift_card_id,
                'code' => $this->relationLoaded('giftCard') ? $this->giftCard?->code : null,
                'expiry_date' => $this->relationLoaded('giftCard')
                    ? dateTimeFormat($this->giftCard?->expiry_date, DateTimeFormat::Date)
                    : null,
            ],
            'amount' => $this->amount->withConvertedDefaultCurrency($this->currency_rate),
            'currency' => $this->currency,
            'currency_rate' => $this->currency_rate,
        ];
    }
}
