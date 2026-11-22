<?php

namespace Modules\Core\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Str;
use Modules\Branch\Models\Branch;
use Modules\Core\Enums\TenantBusinessType;
use Modules\Core\Enums\TenantStatus;

class Tenant extends Model
{
    protected $fillable = [
        'uuid',
        'slug',
        'name',
        'business_type',
        'status',
        'trial_ends_at',
        'onboarding_completed_at',
        'settings',
    ];

    protected static function booted(): void
    {
        static::creating(function (self $tenant): void {
            if (empty($tenant->uuid)) {
                $tenant->uuid = (string) Str::uuid();
            }
        });
    }

    public function branches(): HasMany
    {
        return $this->hasMany(Branch::class, 'tenant_id');
    }

    public function subscriptions(): HasMany
    {
        return $this->hasMany(Subscription::class, 'tenant_id');
    }

    public function activeSubscription(): HasOne
    {
        return $this->hasOne(Subscription::class, 'tenant_id')
            ->whereIn('status', ['trialing', 'active', 'past_due'])
            ->latestOfMany();
    }

    protected function casts(): array
    {
        return [
            'business_type' => TenantBusinessType::class,
            'status' => TenantStatus::class,
            'trial_ends_at' => 'datetime',
            'onboarding_completed_at' => 'datetime',
            'settings' => 'array',
        ];
    }
}
