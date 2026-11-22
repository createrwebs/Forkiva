<?php

namespace Modules\Support;

use DateTimeZone;

class TimeZone
{
    /**
     * Array of all time zones.
     */
    private static array $timeZones;

    /**
     * Get all defined time zones keys.
     */
    public static function keys(): array
    {
        return array_keys(self::all());
    }

    /**
     * Get all defined time zones.
     */
    public static function all(): array
    {
        if (isset(self::$timeZones)) {
            return self::$timeZones;
        }

        $timeZones = DateTimeZone::listIdentifiers();

        return self::$timeZones = array_combine($timeZones, $timeZones);
    }

    /**
     * Get Timezones as a list
     */
    public static function toList(): array
    {
        return collect(static::all())
            ->map(fn ($name, $tKey) => ['id' => $tKey, 'name' => $name])
            ->values()
            ->toArray();
    }
}
