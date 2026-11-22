<?php

namespace Modules\ActivityLog\Support;

class SensitiveProperties
{
    public const FIELDS = [
        'password',
        'current_password',
        'password_confirmation',
        'remember_token',
        'two_factor_secret',
        'two_factor_recovery_codes',
    ];

    /**
     * Remove authentication secrets from new records and legacy response payloads.
     */
    public static function redact(array $properties): array
    {
        foreach ($properties as $key => $value) {
            if (is_string($key) && in_array(strtolower($key), self::FIELDS, true)) {
                unset($properties[$key]);
            } elseif (is_array($value)) {
                $properties[$key] = self::redact($value);
            }
        }

        return $properties;
    }
}
