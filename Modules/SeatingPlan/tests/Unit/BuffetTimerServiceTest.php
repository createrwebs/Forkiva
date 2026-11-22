<?php

namespace Modules\SeatingPlan\Tests\Unit;

use Carbon\CarbonImmutable;
use Modules\SeatingPlan\Services\Buffet\BuffetTimerService;
use PHPUnit\Framework\TestCase;

class BuffetTimerServiceTest extends TestCase
{
    public function test_calculates_headcount_pricing_warning_and_qr_lockout(): void
    {
        $service = new BuffetTimerService;
        $startedAt = CarbonImmutable::parse('2026-11-22 18:00:00');

        $total = $service->calculateHeadcountTotal(adults: 3, adultPrice: '399.00', children: 1, childPrice: '199.00');
        $this->assertSame('1396.00', $total);

        // 80 minutes into a 90-minute buffet -> 10 minutes left -> warning active, still can order
        $warningState = $service->evaluateSession($startedAt, 90, $startedAt->addMinutes(80));
        $this->assertSame(10, $warningState['remaining_minutes']);
        $this->assertTrue($warningState['is_ending_soon']);
        $this->assertFalse($warningState['is_ordering_locked']);

        // 91 minutes into a 90-minute buffet -> expired -> Table QR ordering locked
        $expiredState = $service->evaluateSession($startedAt, 90, $startedAt->addMinutes(91));
        $this->assertSame(0, $expiredState['remaining_minutes']);
        $this->assertTrue($expiredState['is_ordering_locked']);
    }
}
