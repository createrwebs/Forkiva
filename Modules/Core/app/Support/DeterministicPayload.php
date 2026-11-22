<?php

namespace Modules\Core\Support;

final class DeterministicPayload
{
    /**
     * Return a stable SHA-256 fingerprint for a structured payload.
     *
     * Associative keys are sorted recursively while list order remains meaningful.
     */
    public static function fingerprint(array $payload): string
    {
        return hash('sha256', json_encode(
            self::normalize($payload),
            JSON_PRESERVE_ZERO_FRACTION | JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE,
        ));
    }

    /** @return array<mixed> */
    public static function normalize(array $payload): array
    {
        if (! array_is_list($payload)) {
            ksort($payload);
        }

        foreach ($payload as $key => $value) {
            if (is_array($value)) {
                $payload[$key] = self::normalize($value);
            }
        }

        return $payload;
    }
}
