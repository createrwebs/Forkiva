<?php

namespace Modules\Currency\Services;

use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;
use InvalidArgumentException;

final class DefaultCurrencyConverter
{
    /**
     * Convert a source-currency amount to the configured default currency.
     *
     * CurrencyRate stores the number of source-currency units represented by
     * one default-currency unit, so foreign amounts are divided by the frozen
     * rate. Default-currency amounts are never revalued.
     */
    public static function convert(
        BigDecimal|float|int|string $amount,
        string $sourceCurrency,
        BigDecimal|float|int|string $currencyRate,
        int $scale = 6,
    ): BigDecimal {
        $amount = $amount instanceof BigDecimal ? $amount : BigDecimal::of((string) $amount);
        $rate = $currencyRate instanceof BigDecimal ? $currencyRate : BigDecimal::of((string) $currencyRate);

        if ($rate->isLessThanOrEqualTo(BigDecimal::zero())) {
            throw new InvalidArgumentException('Currency rate must be greater than zero.');
        }

        if (strtoupper($sourceCurrency) === strtoupper((string) setting('default_currency', 'JOD'))) {
            return $amount->toScale($scale, RoundingMode::HalfUp);
        }

        return $amount->dividedBy($rate, $scale, RoundingMode::HalfUp);
    }
}
