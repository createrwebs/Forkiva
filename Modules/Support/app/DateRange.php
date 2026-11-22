<?php

namespace Modules\Support;

use Carbon\CarbonImmutable;
use DateTimeInterface;

final class DateRange
{
    /**
     * Normalize a value to the inclusive calendar date used as a range's lower bound.
     *
     * The returned ISO date keeps the value's calendar timezone semantics so it can
     * be bound directly without wrapping the indexed database column.
     *
     * @param  DateTimeInterface|string  $date  Date or date-time value to normalize
     * @return string ISO-8601 calendar date (`Y-m-d`)
     */
    public static function start(DateTimeInterface|string $date): string
    {
        return self::parse($date)->toDateString();
    }

    /**
     * Convert supported input into an immutable value while preserving its timezone.
     *
     * @param  DateTimeInterface|string  $date  Date value accepted by Carbon
     */
    private static function parse(DateTimeInterface|string $date): CarbonImmutable
    {
        return $date instanceof DateTimeInterface
            ? CarbonImmutable::instance($date)
            : CarbonImmutable::parse($date);
    }

    /**
     * Build the exclusive upper bound for an inclusive calendar end date.
     *
     * Advancing to the following calendar day includes every timestamp on the
     * selected end date without an imprecise end-of-day value or SQL date function.
     *
     * @param  DateTimeInterface|string  $date  Inclusive calendar end date
     * @return string ISO-8601 date (`Y-m-d`) for the following calendar day
     */
    public static function endExclusive(DateTimeInterface|string $date): string
    {
        return self::parse($date)->startOfDay()->addDay()->toDateString();
    }
}
