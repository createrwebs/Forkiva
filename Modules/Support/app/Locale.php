<?php

namespace Modules\Support;

use Illuminate\Support\Arr;

class Locale
{
    /**
     * Path of the resource.
     *
     * @var string
     */
    const RESOURCE_PATH = __DIR__.'/../lang/en/languages.php';

    /**
     * Array of all locales.
     */
    private static array $locales;

    /**
     * Get all locale codes.
     */
    public static function codes(): array
    {
        return array_keys(static::all());
    }

    /**
     * Get all locales.
     */
    public static function all(): array
    {
        if (! isset(self::$locales)) {
            $languages = __('support::languages');
            self::$locales = empty($languages) || is_string($languages) ? require self::RESOURCE_PATH : $languages;
        }

        return self::$locales;
    }

    /**
     * Get the name of the given locale code.
     */
    public static function name(string $code): string
    {
        return Arr::get(static::all(), $code);
    }

    /**
     * Get locales as a list
     */
    public static function toList(): array
    {
        return collect(static::all())
            ->map(fn ($name, $tKey) => ['id' => $tKey, 'name' => $name])
            ->values()
            ->toArray();
    }
}
