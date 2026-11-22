<?php

namespace Modules\DailyFinance\Transformers\Api\V1;

use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Modules\Currency\Currency;
use Modules\Support\Money;

class DailyFinanceResource extends JsonResource
{
    /**
     * Keep unavailable amounts null and serialize each authorized section in the branch currency.
     */
    public function toArray(Request $request): array
    {
        $data = $this->resource;
        $currency = $data['meta']['currency'];
        if ($currency === null) {
            return $data;
        }
        foreach ([
            'sales' => ['total', 'dues'],
            'payments' => ['collections', 'refunds', 'net'],
            'movements' => ['cash_in', 'cash_out', 'pay_ins', 'payouts', 'cash_drops'],
            'sessions' => ['expected', 'counted', 'variance'],
        ] as $section => $fields) {
            if ($data[$section] !== null) {
                $data[$section] = $this->moneyFields($data[$section], $fields, $currency);
            }
        }
        if ($data['payments'] !== null) {
            $data['payments']['methods'] = array_map(fn (array $row) => $this->moneyFields($row, ['collections', 'refunds', 'net'], $currency), $data['payments']['methods']);
        }
        if ($data['sessions'] !== null) {
            $data['sessions']['rows'] = array_map(fn (array $row) => [
                ...$this->moneyFields($row, ['expected', 'counted', 'variance'], $currency),
                'opened_at_formatted' => dateTimeFormat($row['opened_at'], timezone: $data['meta']['timezone']),
            ], $data['sessions']['rows']);
        }

        return $data;
    }

    /**
     * Convert only declared monetary fields, preserving identifiers, counts and null evidence.
     */
    private function moneyFields(array $row, array $fields, string $currency): array
    {
        foreach ($fields as $field) {
            $row[$field] = $row[$field] === null ? null : new Money(
                BigDecimal::of($row[$field])->toScale(Currency::subunit($currency), RoundingMode::HalfUp)->toFloat(),
                $currency,
            );
        }

        return $row;
    }
}
