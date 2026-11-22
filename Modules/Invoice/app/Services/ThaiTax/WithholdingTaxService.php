<?php

namespace Modules\Invoice\Services\ThaiTax;

use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;

/**
 * Calculates Thai Withholding Tax (หัก ณ ที่จ่าย) for ภ.ง.ด.3 (individual)
 * and ภ.ง.ด.53 (juristic person) using exact BigDecimal arithmetic.
 */
class WithholdingTaxService
{
    /**
     * @return array{base_amount: string, wht_rate_percent: string, wht_amount: string, net_payable: string, form_type: string}
     */
    public function calculate(string $baseAmount, string $whtRatePercent, bool $isJuristicPerson): array
    {
        $base = BigDecimal::of($baseAmount)->toScale(2, RoundingMode::HALF_UP);
        $rate = BigDecimal::of($whtRatePercent)->dividedBy('100', 6, RoundingMode::HALF_UP);
        $whtAmount = $base->multipliedBy($rate)->toScale(2, RoundingMode::HALF_UP);
        $netPayable = $base->minus($whtAmount)->toScale(2, RoundingMode::HALF_UP);

        return [
            'base_amount' => (string) $base,
            'wht_rate_percent' => (string) BigDecimal::of($whtRatePercent)->toScale(2, RoundingMode::HALF_UP),
            'wht_amount' => (string) $whtAmount,
            'net_payable' => (string) $netPayable,
            'form_type' => $isJuristicPerson ? 'PND53' : 'PND3',
        ];
    }
}
