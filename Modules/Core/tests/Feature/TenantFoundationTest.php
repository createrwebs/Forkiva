<?php

namespace Modules\Core\Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Modules\Branch\Models\Branch;
use Modules\Core\Enums\TenantBusinessType;
use Modules\Core\Enums\TenantStatus;
use Modules\Core\Exceptions\PlanLimitExceededException;
use Modules\Core\Http\Middleware\ResolveTenantMiddleware;
use Modules\Core\Models\Plan;
use Modules\Core\Models\Tenant;
use Modules\Core\Services\Tenant\PlanGateService;
use Modules\Core\Services\Tenant\TenantRegistrationService;
use Modules\Core\Support\TenantContext;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

class TenantFoundationTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        TenantContext::clear();
        parent::tearDown();
    }

    public function test_tenant_registration_creates_tenant_owner_main_branch_and_trial_subscription(): void
    {
        $plan = Plan::query()->create([
            'code' => 'starter',
            'name' => ['en' => 'Starter', 'th' => 'เริ่มต้น'],
            'monthly_price' => '790.00',
            'yearly_price' => '7900.00',
            'currency' => 'THB',
            'limits' => ['max_branches' => 1, 'max_registers' => 2, 'max_users' => 5],
            'features' => ['slip_verify', 'line_oa'],
            'is_active' => true,
        ]);

        $result = app(TenantRegistrationService::class)->register([
            'shop_name' => 'Krua Thai Bistro',
            'slug' => 'krua-thai',
            'business_type' => TenantBusinessType::Fnb->value,
            'owner_name' => 'Somchai Jaidee',
            'email' => 'somchai@kruathai.co.th',
            'password' => 'Secret1234!',
            'phone' => '0812345678',
            'promptpay_id' => '0812345678',
            'plan_code' => $plan->code,
        ]);

        $tenant = $result['tenant'];
        $branch = $result['branch'];
        $subscription = $result['subscription'];
        $owner = $result['owner'];

        $this->assertSame('krua-thai', $tenant->slug);
        $this->assertSame(TenantStatus::Trial, $tenant->status);
        $this->assertSame($tenant->id, $branch->tenant_id);
        $this->assertTrue($branch->is_main);
        $this->assertSame('THB', $branch->currency);
        $this->assertSame($tenant->id, $owner->tenant_id);
        $this->assertSame($plan->id, $subscription->plan_id);
        $this->assertSame('trialing', $subscription->status);
    }

    public function test_has_tenant_global_scope_isolates_branches_across_tenants(): void
    {
        $tenantA = Tenant::query()->create([
            'slug' => 'shop-a',
            'name' => 'Shop A',
            'business_type' => TenantBusinessType::Fnb,
            'status' => TenantStatus::Active,
        ]);
        $tenantB = Tenant::query()->create([
            'slug' => 'shop-b',
            'name' => 'Shop B',
            'business_type' => TenantBusinessType::Retail,
            'status' => TenantStatus::Active,
        ]);

        $branchA = TenantContext::runFor($tenantA, fn () => Branch::factory()->create(['name' => ['en' => 'Branch A']]));
        $branchB = TenantContext::runFor($tenantB, fn () => Branch::factory()->create(['name' => ['en' => 'Branch B']]));

        $this->assertSame($tenantA->id, $branchA->tenant_id);
        $this->assertSame($tenantB->id, $branchB->tenant_id);

        $visibleToA = TenantContext::runFor($tenantA, fn () => Branch::query()->pluck('id')->all());
        $visibleToB = TenantContext::runFor($tenantB, fn () => Branch::query()->pluck('id')->all());

        $this->assertSame([$branchA->id], $visibleToA);
        $this->assertSame([$branchB->id], $visibleToB);
    }

    public function test_plan_gate_enforces_feature_flags_and_resource_quotas(): void
    {
        $plan = Plan::query()->create([
            'code' => 'starter',
            'name' => ['en' => 'Starter'],
            'monthly_price' => '790.00',
            'yearly_price' => '7900.00',
            'currency' => 'THB',
            'limits' => ['max_branches' => 1],
            'features' => ['slip_verify'],
            'is_active' => true,
        ]);

        $tenant = Tenant::query()->create([
            'slug' => 'quota-shop',
            'name' => 'Quota Shop',
            'business_type' => TenantBusinessType::Fnb,
            'status' => TenantStatus::Active,
        ]);
        $tenant->subscriptions()->create([
            'plan_id' => $plan->id,
            'billing_cycle' => 'monthly',
            'status' => 'active',
            'current_period_start' => now(),
            'current_period_end' => now()->addMonth(),
        ]);

        $gate = app(PlanGateService::class);

        $this->assertTrue($gate->canUseFeature($tenant, 'slip_verify'));
        $this->assertFalse($gate->canUseFeature($tenant, 'delivery_aggregator'));
        $this->assertTrue($gate->withinLimit($tenant, 'max_branches', 0));
        $this->assertFalse($gate->withinLimit($tenant, 'max_branches', 1));

        $this->expectException(PlanLimitExceededException::class);
        $gate->assertWithinLimit($tenant, 'max_branches', 1);
    }

    public function test_resolve_tenant_middleware_sets_context_and_blocks_suspended_tenant(): void
    {
        $active = Tenant::query()->create([
            'slug' => 'active-cafe',
            'name' => 'Active Cafe',
            'business_type' => TenantBusinessType::Fnb,
            'status' => TenantStatus::Active,
        ]);
        $suspended = Tenant::query()->create([
            'slug' => 'suspended-cafe',
            'name' => 'Suspended Cafe',
            'business_type' => TenantBusinessType::Fnb,
            'status' => TenantStatus::Suspended,
        ]);

        $middleware = new ResolveTenantMiddleware;

        $request = Request::create('/api/v1/branches', 'GET');
        $request->headers->set('X-Tenant-Slug', $active->slug);

        $capturedTenantId = null;
        $middleware->handle($request, function () use (&$capturedTenantId) {
            $capturedTenantId = TenantContext::id();

            return response()->json(['ok' => true]);
        });

        $this->assertSame($active->id, $capturedTenantId);
        $this->assertNull(TenantContext::id());

        $blockedRequest = Request::create('/api/v1/branches', 'GET');
        $blockedRequest->headers->set('X-Tenant-Slug', $suspended->slug);

        $this->expectException(HttpException::class);
        $middleware->handle($blockedRequest, fn () => response()->json(['ok' => true]));
    }

    public function test_has_tags_cache_flushes_only_current_tenant_tag(): void
    {
        $tenantA = Tenant::query()->create([
            'slug' => 'cache-a',
            'name' => 'Cache A',
            'business_type' => TenantBusinessType::Fnb,
            'status' => TenantStatus::Active,
        ]);
        $tenantB = Tenant::query()->create([
            'slug' => 'cache-b',
            'name' => 'Cache B',
            'business_type' => TenantBusinessType::Fnb,
            'status' => TenantStatus::Active,
        ]);

        Cache::tags("tenant:{$tenantA->id}:branches")->put('k', 'A', 60);
        Cache::tags("tenant:{$tenantB->id}:branches")->put('k', 'B', 60);

        TenantContext::runFor($tenantA, function (): void {
            Branch::factory()->create(['name' => ['en' => 'Flush A']]);
        });

        $this->assertNull(Cache::tags("tenant:{$tenantA->id}:branches")->get('k'));
        $this->assertSame('B', Cache::tags("tenant:{$tenantB->id}:branches")->get('k'));
    }
}
