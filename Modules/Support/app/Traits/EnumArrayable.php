<?php

namespace Modules\Support\Traits;

use BackedEnum;
use Illuminate\Support\Arr;

trait EnumArrayable
{
    public static function names(): array
    {
        return array_column(self::cases(), 'name');
    }

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }

    public static function toArray(): array
    {
        return array_combine(self::values(), self::names());
    }

    public static function toDatabaseString(): string
    {
        return implode(',', array_map(fn ($key) => "'$key'", self::values()));
    }

    /**
     * Return a random value of enum values
     */
    public static function randomValue(BackedEnum|array|string|null $except = null): string
    {
        $randomCase = self::randomCase($except);

        return $randomCase->value;
    }

    /**
     * Return a random name of enum names
     */
    public static function randomName(BackedEnum|array|string|null $except = null): string
    {
        $randomCase = self::randomCase($except);

        return $randomCase->name;
    }

    /**
     * Return a random case of enum cases
     */
    public static function randomCase(BackedEnum|array|string|null $except = null): self
    {
        $cases = self::cases();
        if (! empty($except)) {
            $except = Arr::map(Arr::wrap($except), fn ($value) => $value instanceof BackedEnum ? $value->value : $value);
            $cases = Arr::where($cases, fn ($case) => ! in_array($case->value, $except));
        }

        // This will throw an exception if the cases array is empty:
        // "You requested 1 or more items, but there are only 0 items available"
        return Arr::random($cases);
    }
}
