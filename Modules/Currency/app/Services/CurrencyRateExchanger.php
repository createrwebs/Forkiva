<?php

namespace Modules\Currency\Services;

use Swap\Swap;

readonly class CurrencyRateExchanger
{
    /**
     * Create a new CurrencyRateExchanger instance.
     */
    public function __construct(private Swap $swap) {}

    /**
     * Exchange to the latest currency rate.
     */
    public function exchange(string $fromCurrency, string $toCurrency): ?float
    {
        return $this->swap->latest("$fromCurrency/$toCurrency")->getValue();
    }
}
