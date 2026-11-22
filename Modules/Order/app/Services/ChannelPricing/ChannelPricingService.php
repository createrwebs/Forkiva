<?php

namespace Modules\Order\Services\ChannelPricing;

use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;

class ChannelPricingService
{
    /**
     * Calculate Thai delivery platform GP commission, VAT on GP (7%), and net receivable
     * using exact decimal arithmetic.
     *
     * @return array{gross_amount: string, gp_amount: string, gp_vat_amount: string, total_deduction: string, net_receivable: string}
     */
    public function calculateCommission(
        string $grossAmount,
        string $gpPercent,
        bool $vatOnGp = true,
        string $vatRatePercent = '7.00',
        int $scale = 2
    ): array {
        $gross = BigDecimal::of($grossAmount)->toScale($scale, RoundingMode::HALF_UP);
        $gpRate = BigDecimal::of($gpPercent)->dividedBy('100', 6, RoundingMode::HALF_UP);
        $gpAmount = $gross->multipliedBy($gpRate)->toScale($scale, RoundingMode::HALF_UP);

        $gpVatAmount = BigDecimal::zero()->toScale($scale);
        if ($vatOnGp) {
            $vatRate = BigDecimal::of($vatRatePercent)->dividedBy('100', 6, RoundingMode::HALF_UP);
            $gpVatAmount = $gpAmount->multipliedBy($vatRate)->toScale($scale, RoundingMode::HALF_UP);
        }

        $totalDeduction = $gpAmount->plus($gpVatAmount)->toScale($scale, RoundingMode::HALF_UP);
        $netReceivable = $gross->minus($totalDeduction)->toScale($scale, RoundingMode::HALF_UP);

        return [
            'gross_amount' => (string) $gross,
            'gp_amount' => (string) $gpAmount,
            'gp_vat_amount' => (string) $gpVatAmount,
            'total_deduction' => (string) $totalDeduction,
            'net_receivable' => (string) $netReceivable,
        ];
    }
}
