<?php

namespace Modules\Support\Security;

class PasskeyConfigurationValidator
{
    /**
     * Determine whether every configured origin is safe and compatible with the RP ID.
     */
    public static function valid(string $relyingPartyId, mixed $origins): bool
    {
        if (! self::validRelyingPartyId($relyingPartyId)
            || ! is_array($origins)
            || $origins === []) {
            return false;
        }

        foreach ($origins as $origin) {
            if (! is_string($origin)
                || ! self::secureOrigin($origin)
                || ! self::originMatchesRelyingParty($origin, $relyingPartyId)) {
                return false;
            }
        }

        return true;
    }

    /**
     * Allow HTTP only for loopback development origins.
     */
    public static function secureOrigin(string $origin): bool
    {
        $parts = self::originParts($origin);
        if ($parts === null) {
            return false;
        }

        if ($parts['scheme'] === 'https') {
            return true;
        }

        return $parts['scheme'] === 'http' && self::loopbackHost($parts['host']);
    }

    /**
     * Ensure the RP ID is the origin host or one of its domain suffixes.
     */
    public static function originMatchesRelyingParty(string $origin, string $relyingPartyId): bool
    {
        $parts = self::originParts($origin);
        $relyingPartyId = strtolower($relyingPartyId);
        if ($parts === null || ! self::validRelyingPartyId($relyingPartyId)) {
            return false;
        }

        $host = $parts['host'];
        if (filter_var($relyingPartyId, FILTER_VALIDATE_IP)) {
            return $host === $relyingPartyId;
        }

        return $host === $relyingPartyId || str_ends_with($host, '.'.$relyingPartyId);
    }

    /**
     * Validate the host-only relying-party identifier.
     */
    private static function validRelyingPartyId(string $relyingPartyId): bool
    {
        if ($relyingPartyId === ''
            || $relyingPartyId !== strtolower($relyingPartyId)
            || str_ends_with($relyingPartyId, '.')) {
            return false;
        }

        if ($relyingPartyId === 'localhost') {
            return true;
        }
        if (preg_match('/^[0-9.]+$/', $relyingPartyId)) {
            return filter_var($relyingPartyId, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4) !== false;
        }

        return str_contains($relyingPartyId, '.')
            && (bool) preg_match(
                '/^(?:localhost|(?:[a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])?\.)*[a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])?|(?:\d{1,3}\.){3}\d{1,3})$/',
                $relyingPartyId,
            );
    }

    /**
     * Parse an exact web origin without credentials, paths, queries, or fragments.
     */
    private static function originParts(string $origin): ?array
    {
        $parts = parse_url($origin);
        if (! is_array($parts)
            || ! isset($parts['scheme'], $parts['host'])
            || isset($parts['user'], $parts['pass'], $parts['query'], $parts['fragment'])
            || isset($parts['path'])) {
            return null;
        }

        $scheme = strtolower($parts['scheme']);
        $host = strtolower($parts['host']);
        $port = $parts['port'] ?? null;
        if (! in_array($scheme, ['http', 'https'], true)
            || ($port !== null && ($port < 1 || $port > 65535))) {
            return null;
        }

        $expected = $scheme.'://'.$host.($port === null ? '' : ':'.$port);

        return hash_equals($expected, strtolower($origin))
            ? ['scheme' => $scheme, 'host' => $host]
            : null;
    }

    /**
     * Determine whether a host is a browser-trusted local development host.
     */
    private static function loopbackHost(string $host): bool
    {
        return $host === 'localhost'
            || str_ends_with($host, '.localhost')
            || $host === '127.0.0.1';
    }
}
