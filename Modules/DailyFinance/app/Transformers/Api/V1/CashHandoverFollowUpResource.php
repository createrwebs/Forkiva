<?php

namespace Modules\DailyFinance\Transformers\Api\V1;

use Brick\Math\BigDecimal;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Modules\Currency\Currency;
use Modules\Support\Money;

class CashHandoverFollowUpResource extends JsonResource
{
    /**
     * Preserve each closing currency and expose exact strings alongside presentation amounts.
     */
    public function toArray(Request $request): array
    {
        return [
            ...$this->resource,
            'balances' => array_map(function (array $balance): array {
                $currency = $balance['currency'];
                $unhanded = BigDecimal::of($balance['unhanded'])->toScale(Currency::subunit($currency));
                $pending = BigDecimal::of($balance['pending'])->toScale(Currency::subunit($currency));

                return [
                    'currency' => $currency,
                    'unhanded' => new Money($unhanded->toFloat(), $currency),
                    'unhanded_decimal' => (string) $unhanded,
                    'pending' => new Money($pending->toFloat(), $currency),
                    'pending_decimal' => (string) $pending,
                ];
            }, $this->resource['balances']),
        ];
    }
}
