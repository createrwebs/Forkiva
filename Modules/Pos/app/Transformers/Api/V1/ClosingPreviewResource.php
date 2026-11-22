<?php

namespace Modules\Pos\Transformers\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Modules\Support\Money;

class ClosingPreviewResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $session = $this->resource['session'];
        $review = $this->resource['review'];

        return [
            ...(new PosSessionShowResource($session))->resolve($request),
            'declared_cash' => $review['declared_cash_decimal'] === null ? null : new Money((float) $review['declared_cash_decimal'], $session->currency),
            'cash_over_short' => $review['cash_difference'] === null ? null : new Money((float) $review['cash_difference'], $session->currency),
            'review' => [
                ...$review,
                'cash_difference' => $review['cash_difference'] === null ? null : new Money((float) $review['cash_difference'], $session->currency),
                'allowed_difference' => new Money((float) $review['allowed_difference'], $session->currency),
            ],
        ];
    }
}
