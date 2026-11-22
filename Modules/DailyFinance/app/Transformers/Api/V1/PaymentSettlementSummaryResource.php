<?php

namespace Modules\DailyFinance\Transformers\Api\V1;

use Brick\Math\BigDecimal;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Modules\Currency\Currency;
use Modules\Support\Money;

class PaymentSettlementSummaryResource extends JsonResource
{
    /**
     * Present current outstanding balances in each settlement's original currency.
     */
    public function toArray(Request $request): array
    {
        return [
            ...$this->resource,
            'balances' => array_map(function (array $balance): array {
                $amount = BigDecimal::of($balance['remaining'])->toScale(Currency::subunit($balance['currency']));

                return [
                    ...$balance,
                    'remaining' => new Money($amount->toFloat(), $balance['currency']),
                    'remaining_decimal' => (string) $amount,
                ];
            }, $this->resource['balances']),
        ];
    }
}
