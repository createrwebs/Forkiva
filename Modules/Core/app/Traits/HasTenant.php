<?php

namespace Modules\Core\Traits;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Core\Models\Tenant;
use Modules\Core\Support\TenantContext;

/**
 * Enforces tenant isolation on Eloquent models whenever a TenantContext is active.
 */
trait HasTenant
{
    public static function bootHasTenant(): void
    {
        static::addGlobalScope('tenant', function (Builder $builder): void {
            $tenantId = TenantContext::id();

            if ($tenantId !== null) {
                $builder->where($builder->getModel()->qualifyColumn('tenant_id'), $tenantId);
            }
        });

        static::creating(function ($model): void {
            $tenantId = TenantContext::id();

            if ($tenantId !== null && $model->getAttribute('tenant_id') === null) {
                $model->setAttribute('tenant_id', $tenantId);
            }
        });
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class, 'tenant_id');
    }

    public function scopeForTenant(Builder $query, Tenant|int $tenant): void
    {
        $tenantId = $tenant instanceof Tenant ? (int) $tenant->getKey() : $tenant;
        $query->withoutGlobalScope('tenant')
            ->where($this->qualifyColumn('tenant_id'), $tenantId);
    }

    public function scopeWithoutTenantScope(Builder $query): void
    {
        $query->withoutGlobalScope('tenant');
    }
}
