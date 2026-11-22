<?php

namespace Modules\Order\Tests\Unit;

use InvalidArgumentException;
use Modules\Order\Services\ChannelPricing\ChannelPricingService;
use Modules\Order\Services\OrderSplit\OrderSplitService;
use PHPUnit\Framework\TestCase;

class OrderSplitAndChannelPricingTest extends TestCase
{
    public function test_split_equal_conserves_exact_total_with_satang_remainder(): void
    {
        $service = new OrderSplitService;

        $parts = $service->splitEqualAmounts('100.00', 3, 2);

        $this->assertSame(['33.34', '33.33', '33.33'], $parts);
        $this->assertSame('100.00', number_format(array_sum(array_map('floatval', $parts)), 2, '.', ''));
    }

    public function test_split_equal_rejects_fewer_than_two_ways(): void
    {
        $this->expectException(InvalidArgumentException::class);

        (new OrderSplitService)->splitEqualAmounts('100.00', 1, 2);
    }

    public function test_channel_pricing_calculates_thai_gp_and_vat_on_gp(): void
    {
        $service = new ChannelPricingService;

        // 1,000 THB order on LINE MAN with 30% GP + 7% VAT on GP
        // GP = 300.00, VAT on GP = 21.00, Net Receivable = 679.00
        $result = $service->calculateCommission(
            grossAmount: '1000.00',
            gpPercent: '30.00',
            vatOnGp: true,
            vatRatePercent: '7.00'
        );

        $this->assertSame('300.00', $result['gp_amount']);
        $this->assertSame('21.00', $result['gp_vat_amount']);
        $this->assertSame('321.00', $result['total_deduction']);
        $this->assertSame('679.00', $result['net_receivable']);
    }
}
