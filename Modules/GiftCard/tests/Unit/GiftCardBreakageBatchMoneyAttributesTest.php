<?php

namespace Modules\GiftCard\Tests\Unit;

use Modules\GiftCard\Models\GiftCardBreakageBatch;
use Modules\Support\Money;
use Tests\TestCase;

class GiftCardBreakageBatchMoneyAttributesTest extends TestCase
{
    public function test_total_amount_uses_the_frozen_batch_currency(): void
    {
        $batch = new GiftCardBreakageBatch([
            'currency' => 'USD',
            'total_amount' => '84.1741',
        ]);

        $this->assertInstanceOf(Money::class, $batch->total_amount);
        $this->assertSame('USD', $batch->total_amount->currency());
        $this->assertEquals(84.1741, $batch->total_amount->amount());
    }
}
