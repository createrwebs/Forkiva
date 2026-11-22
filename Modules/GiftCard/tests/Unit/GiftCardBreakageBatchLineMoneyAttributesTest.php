<?php

namespace Modules\GiftCard\Tests\Unit;

use Modules\GiftCard\Models\GiftCardBreakageBatchLine;
use Modules\Support\Money;
use Tests\TestCase;

class GiftCardBreakageBatchLineMoneyAttributesTest extends TestCase
{
    public function test_amount_uses_the_frozen_line_currency_without_loading_the_batch(): void
    {
        $line = new GiftCardBreakageBatchLine([
            'amount' => '12.3400',
            'currency' => 'USD',
            'currency_rate' => '0.70920000',
        ]);

        $this->assertInstanceOf(Money::class, $line->amount);
        $this->assertSame('USD', $line->amount->currency());
        $this->assertEquals(12.34, $line->amount->amount());
        $this->assertFalse($line->relationLoaded('batch'));
    }
}
