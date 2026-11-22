<?php

namespace Modules\Core\Services\Tenant;

use Modules\Core\Exceptions\PlanLimitExceededException;
use Modules\Core\Models\Plan;
use Modules\Core\Models\Tenant;

class PlanGateService
{
    public function activePlan(Tenant $tenant): ?Plan
    {
        return $tenant->activeSubscription()->with('plan')->first()?->plan;
    }

    public function canUseFeature(Tenant $tenant, string $feature): bool
    {
        $plan = $this->activePlan($tenant);

        return $plan !== null && $plan->hasFeature($feature);
    }

    public function withinLimit(Tenant $tenant, string $resource, int $currentCount): bool
    {
        $plan = $this->activePlan($tenant);

        if ($plan === null) {
            return false;
        }

        $limit = $plan->limitFor($resource);

        // Null or negative limit means unlimited in this plan.
        if ($limit === null || $limit < 0) {
            return true;
        }

        return $currentCount < $limit;
    }

    public function assertFeature(Tenant $tenant, string $feature): void
    {
        if (! $this->canUseFeature($tenant, $feature)) {
            throw new PlanLimitExceededException("Current subscription plan does not include feature [{$feature}].");
        }
    }

    public function assertWithinLimit(Tenant $tenant, string $resource, int $currentCount): void
    {
        if (! $this->withinLimit($tenant, $resource, $currentCount)) {
            throw new PlanLimitExceededException("Current subscription plan limit exceeded for [{$resource}].");
        }
    }
}
