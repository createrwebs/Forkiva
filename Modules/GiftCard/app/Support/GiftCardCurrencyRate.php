<?php

namespace Modules\GiftCard\Support;

use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;
use InvalidArgumentException;
use Modules\Currency\Models\CurrencyRate;

final class GiftCardCurrencyRate
{
    /**
     * Return default/base currency represented by one Gift Card currency unit.
     */
    public static function toDefault(string $currency): string
    {
        $defaultCurrency = strtoupper((string) setting('default_currency', 'JOD'));
        if (strtoupper($currency) === $defaultCurrency) {
            return '1.00000000';
        }

        // CurrencyRate stores default -> target. Accounting snapshots freeze
        // the inverse because their rate is always source -> default/base.
        $configuredRate = BigDecimal::of((string) CurrencyRate::for($currency));
        if ($configuredRate->isLessThanOrEqualTo(BigDecimal::zero())) {
            throw new InvalidArgumentException(__('giftcard::messages.currency_rate_must_be_greater_than_zero'));
        }

        return (string) BigDecimal::one()->dividedBy($configuredRate, 8, RoundingMode::HalfUp);
    }
}
