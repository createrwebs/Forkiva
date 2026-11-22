<?php

namespace Modules\Core\Support;

use Modules\Core\Exceptions\MissingTenantContextException;
use Modules\Core\Models\Tenant;

/**
 * Holds the active SaaS tenant identifier for the current request, job, or command.
 */
final class TenantContext
{
    private static ?int $tenantId = null;

    public static function id(): ?int
    {
        return self::$tenantId;
    }

    public static function requireId(): int
    {
        if (self::$tenantId === null) {
            throw MissingTenantContextException::create();
        }

        return self::$tenantId;
    }

    public static function set(Tenant|int|null $tenant): void
    {
        self::$tenantId = $tenant instanceof Tenant ? (int) $tenant->getKey() : $tenant;
    }

    public static function clear(): void
    {
        self::$tenantId = null;
    }

    /**
     * Execute a callback within the given tenant context and restore the prior state.
     *
     * @template TReturn
     *
     * @param  callable(): TReturn  $callback
     * @return TReturn
     */
    public static function runFor(Tenant|int $tenant, callable $callback): mixed
    {
        $previous = self::$tenantId;
        self::set($tenant);

        try {
            return $callback();
        } finally {
            self::$tenantId = $previous;
        }
    }

    /**
     * Execute a callback without any tenant scope and restore the prior state.
     *
     * @template TReturn
     *
     * @param  callable(): TReturn  $callback
     * @return TReturn
     */
    public static function withoutTenant(callable $callback): mixed
    {
        $previous = self::$tenantId;
        self::$tenantId = null;

        try {
            return $callback();
        } finally {
            self::$tenantId = $previous;
        }
    }
}
