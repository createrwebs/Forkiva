<?php

namespace Modules\Core\Queue\Middleware;

use Closure;
use Modules\Core\Support\TenantContext;

/**
 * Restores and cleans up TenantContext around queued job execution so
 * long-lived queue workers never leak tenant state between jobs.
 */
class RestoreTenantContext
{
    public function __construct(private ?int $tenantId = null) {}

    public function handle(object $job, Closure $next): void
    {
        $resolvedTenantId = $this->tenantId ?? (property_exists($job, 'tenantId') ? $job->tenantId : null);

        if ($resolvedTenantId === null) {
            $next($job);

            return;
        }

        TenantContext::runFor((int) $resolvedTenantId, function () use ($job, $next): void {
            $next($job);
        });
    }
}
