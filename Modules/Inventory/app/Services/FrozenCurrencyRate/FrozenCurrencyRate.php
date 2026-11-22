<?php

namespace Modules\Inventory\Services\FrozenCurrencyRate;

use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;
use Illuminate\Validation\ValidationException;
use Modules\Currency\Models\CurrencyRate;

final class FrozenCurrencyRate
{
    /**
     * Resolve a configured source-to-base rate without silently assuming parity.
     */
    public static function for(string $currency): string
    {
        $currency = strtoupper($currency);
        $baseCurrency = strtoupper((string) setting('default_currency', 'JOD'));

        if ($currency === $baseCurrency) {
            return '1.00000000';
        }

        $storedRate = CurrencyRate::query()
            ->where('currency', $currency)
            ->first()?->getRawOriginal('rate');

        if ($storedRate === null) {
            throw ValidationException::withMessages([
                'currency_rate' => __('inventory::messages.stock_movement_currency_rate_missing', [
                    'currency' => $currency,
                ]),
            ]);
        }

        $rate = BigDecimal::of((string) $storedRate);
        if ($rate->isLessThanOrEqualTo(BigDecimal::zero())) {
            throw ValidationException::withMessages([
                'currency_rate' => __('inventory::messages.stock_movement_currency_rate_invalid'),
            ]);
        }

        return (string) $rate->toScale(8, RoundingMode::HalfUp);
    }
}
