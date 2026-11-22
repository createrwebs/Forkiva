<?php

namespace Modules\Notification\Support;

class NotificationSearch
{
    public const MIN_LENGTH = 3;

    /**
     * Convert user input into required FULLTEXT prefix terms.
     */
    public static function booleanQuery(string $search): string
    {
        preg_match_all('/[\p{L}\p{M}\p{N}_]+/u', mb_strtolower($search), $matches);

        $tokens = array_values(array_unique(array_filter(
            $matches[0] ?? [],
            fn (string $token): bool => mb_strlen($token) >= self::MIN_LENGTH,
        )));

        return implode(' ', array_map(
            fn (string $token): string => "+{$token}*",
            $tokens,
        ));
    }
}
