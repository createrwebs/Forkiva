<?php

namespace Modules\Support\Tests\Unit;

use Modules\Support\Money;
use Tests\TestCase;

class MoneyTest extends TestCase
{
    public function test_zero_checks_preserve_integer_float_and_small_amount_semantics(): void
    {
        foreach ([0, 0.0, -0.0] as $amount) {
            $this->assertTrue((new Money($amount, 'JOD'))->isZero());
        }

        foreach ([1, -1, 0.0001, -0.0001] as $amount) {
            $this->assertFalse((new Money($amount, 'JOD'))->isZero());
        }
    }

    public function test_round_normalizes_negative_zero(): void
    {
        $money = (new Money(-0.0004, 'JOD'))->round();

        $this->assertSame(0, $money->amount());
        $this->assertStringNotContainsString('-', $money->format('JOD', 'en'));
    }
}
