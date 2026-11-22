<?php

namespace Modules\Reservation\Support;

use DateTimeImmutable;
use Illuminate\Support\Arr;

class TimeInput
{
    /**
     * Accepted time formats.
     *
     * @var list<string>
     */
    protected const FORMATS = ['H:i', 'h:i A', 'h:i a'];

    /**
     * Normalize a time string to 24-hour `H:i` format when possible.
     */
    public static function normalize(mixed $value): mixed
    {
        return self::format($value, 'H:i');
    }

    /**
     * Format a supported time value into the requested output format.
     */
    public static function format(mixed $value, string $outputFormat): mixed
    {
        if (blank($value) || ! is_scalar($value)) {
            return $value;
        }

        $rawValue = trim((string) $value);

        foreach (self::FORMATS as $format) {
            $date = DateTimeImmutable::createFromFormat('!'.$format, $rawValue);
            $errors = DateTimeImmutable::getLastErrors();

            if ($date !== false && Arr::get($errors, 'warning_count', 0) === 0 && Arr::get($errors, 'error_count', 0) === 0) {
                return $date->format($outputFormat);
            }
        }

        return $value;
    }

    /**
     * Determine if the given value is a supported time string.
     */
    public static function isValid(mixed $value): bool
    {
        if (! is_scalar($value) || $value === '') {
            return false;
        }

        $rawValue = trim((string) $value);

        foreach (self::FORMATS as $format) {
            $date = DateTimeImmutable::createFromFormat('!'.$format, $rawValue);
            $errors = DateTimeImmutable::getLastErrors();

            if ($date !== false && Arr::get($errors, 'warning_count', 0) === 0 && Arr::get($errors, 'error_count', 0) === 0) {
                return true;
            }
        }

        return false;
    }
}
