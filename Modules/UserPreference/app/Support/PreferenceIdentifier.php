<?php

namespace Modules\UserPreference\Support;

use BackedEnum;
use InvalidArgumentException;

final class PreferenceIdentifier
{
    /**
     * Validate a database-safe key shared by service and HTTP callers.
     */
    public static function normalize(string|BackedEnum $identifier): string
    {
        $value = $identifier instanceof BackedEnum ? $identifier->value : $identifier;

        if (! is_string($value) || strlen($value) > 191 || preg_match('/^[a-z0-9]+(?:[._:-][a-z0-9]+)*$/', $value) !== 1) {
            throw new InvalidArgumentException('Invalid user preference identifier.');
        }

        return $value;
    }
}
