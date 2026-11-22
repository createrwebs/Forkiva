<?php

namespace Modules\DailyFinance\Transformers\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Modules\Support\Money;

class CashHandoverFollowUpSessionResource extends JsonResource
{
    /**
     * Present the source identity only from loaded historical relations and use shared date formatting.
     */
    public function toArray(Request $request): array
    {
        $session = $this->resource['session'];
        $currency = $this->resource['currency'];

        return [
            'id' => $session->id,
            'register' => $session->relationLoaded('posRegister') ? $session->posRegister?->name : null,
            'cashier' => $session->relationLoaded('openedBy') ? $session->openedBy?->name : null,
            'opened_at_formatted' => dateTimeFormat($session->opened_at),
            'closed_at_formatted' => dateTimeFormat($session->closed_at),
            'currency' => $currency,
            'unhanded' => new Money($this->resource['available']->toFloat(), $currency),
            'pending' => new Money($this->resource['pending']->toFloat(), $currency),
            'pending_count' => $this->resource['pending_count'],
        ];
    }
}
