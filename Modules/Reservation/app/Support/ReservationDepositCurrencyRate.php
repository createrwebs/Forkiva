<?php

namespace Modules\Reservation\Support;

use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;
use Illuminate\Validation\ValidationException;
use Modules\Currency\Models\CurrencyRate;

final class ReservationDepositCurrencyRate
{
    /**
     * Resolve the frozen deposit-currency-to-default-currency rate.
     */
    public static function toDefault(string $currency): string
    {
        $currency = strtoupper($currency);
        $defaultCurrency = strtoupper((string) setting('default_currency', 'JOD'));

        if ($currency === $defaultCurrency) {
            return '1.00000000';
        }

        $configuredRate = CurrencyRate::query()
            ->where('currency', $currency)
            ->first()
            ?->getRawOriginal('rate');

        if ($configuredRate === null) {
            throw ValidationException::withMessages([
                'currency_rate' => [__('reservation::messages.deposit_currency_rate_missing', [
                    'currency' => $currency,
                ])],
            ]);
        }

        $rate = BigDecimal::of((string) $configuredRate);
        if ($rate->isLessThanOrEqualTo(BigDecimal::zero())) {
            throw ValidationException::withMessages([
                'currency_rate' => [__('reservation::messages.deposit_currency_rate_invalid')],
            ]);
        }

        return (string) BigDecimal::one()->dividedBy($rate, 8, RoundingMode::HalfUp);
    }

    /**
     * Convert a source-to-default rate for the legacy Money display helper.
     */
    public static function forMoneyConversion(int|float|string $sourceToDefaultRate): string
    {
        $rate = BigDecimal::of((string) $sourceToDefaultRate);

        if ($rate->isLessThanOrEqualTo(BigDecimal::zero())) {
            throw ValidationException::withMessages([
                'currency_rate' => [__('reservation::messages.deposit_currency_rate_invalid')],
            ]);
        }

        return (string) BigDecimal::one()->dividedBy($rate, 8, RoundingMode::HalfUp);
    }
}
