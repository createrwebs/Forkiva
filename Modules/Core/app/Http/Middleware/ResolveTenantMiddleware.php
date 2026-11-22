<?php

namespace Modules\Core\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Modules\Core\Models\Tenant;
use Modules\Core\Support\TenantContext;
use Symfony\Component\HttpFoundation\Response;

class ResolveTenantMiddleware
{
    public function handle(Request $request, Closure $next): Response
    {
        $tenant = $this->resolveTenant($request);

        if ($tenant !== null && ! $tenant->status->isAccessible()) {
            abort(403, 'Tenant account is not active.');
        }

        $previous = TenantContext::id();
        TenantContext::set($tenant);

        try {
            return $next($request);
        } finally {
            TenantContext::set($previous);
        }
    }

    private function resolveTenant(Request $request): ?Tenant
    {
        $slug = $request->header('X-Tenant-Slug');

        if (is_string($slug) && trim($slug) !== '') {
            return Tenant::query()->where('slug', trim($slug))->firstOrFail();
        }

        $tenantId = $request->header('X-Tenant-Id');

        if (is_numeric($tenantId)) {
            return Tenant::query()->findOrFail((int) $tenantId);
        }

        $user = $request->user();

        if ($user !== null && ! empty($user->tenant_id)) {
            return Tenant::query()->find((int) $user->tenant_id);
        }

        return null;
    }
}
