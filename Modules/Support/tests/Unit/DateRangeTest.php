<?php

namespace Modules\Support\Tests\Unit;

use Carbon\CarbonImmutable;
use Modules\Support\DateRange;
use PHPUnit\Framework\TestCase;

class DateRangeTest extends TestCase
{
    public function test_it_builds_half_open_calendar_date_boundaries(): void
    {
        $date = CarbonImmutable::parse('2024-02-29 18:30:00', 'Asia/Amman');

        $this->assertSame('2024-02-29', DateRange::start($date));
        $this->assertSame('2024-03-01', DateRange::endExclusive($date));
        $this->assertSame('2024-02-29 18:30:00', $date->toDateTimeString());
    }

    public function test_it_normalizes_string_dates_in_the_application_timezone(): void
    {
        $this->assertSame('2026-09-06', DateRange::start('2026-09-06 23:59:59'));
        $this->assertSame('2026-09-07', DateRange::endExclusive('2026-09-06'));
    }
}
