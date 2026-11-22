<?php

namespace Modules\Support;

class TimeFormats
{
    /**
     * Path of the resource.
     *
     * @var string
     */
    public const RESOURCE_PATH = __DIR__.'/../resources/data/time_formats.php';

    /**
     * Array of all formats.
     */
    private static array $formats;

    /**
     * Get time format keys
     */
    public static function keys(): array
    {
        return array_keys(self::all());
    }

    /**
     * Get all formats.
     */
    public static function all(): array
    {
        if (! isset(self::$formats)) {
            self::$formats = require self::RESOURCE_PATH;
        }

        return self::$formats;
    }

    /**
     * Get time formats as a list
     */
    public static function toList(): array
    {
        return collect(static::all())
            ->map(fn ($name, $tKey) => ['id' => $tKey, 'name' => $name])
            ->values()
            ->toArray();
    }
}
