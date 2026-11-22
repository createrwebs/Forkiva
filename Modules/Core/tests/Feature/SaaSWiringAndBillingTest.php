<?php

namespace Modules\Core\Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Core\Enums\TenantBusinessType;
use Modules\Core\Enums\TenantStatus;
use Modules\Core\Models\Plan;
use Modules\Core\Models\Tenant;
use Modules\Core\Queue\Middleware\RestoreTenantContext;
use Modules\Core\Services\Tenant\SubscriptionBillingService;
use Modules\Core\Support\TenantContext;
use Modules\Printer\Enum\PrintContentType;
use Modules\Printer\Factories\PrintContentFactory;
use Modules\Printer\Factories\PrintContents\PrintLabelContentFactory;
use Tests\TestCase;

class SaaSWiringAndBillingTest extends TestCase
{
    use RefreshDatabase;

    public function test_queue_middleware_restores_and_clears_tenant_context(): void
    {
        TenantContext::clear();
        $middleware = new RestoreTenantContext(77);
        $captured = null;

        $middleware->handle(new \stdClass, function () use (&$captured): void {
            $captured = TenantContext::id();
        });

        $this->assertSame(77, $captured);
        $this->assertNull(TenantContext::id());
    }

    public function test_subscription_billing_webhook_is_idempotent_and_activates_tenant(): void
    {
        $plan = Plan::query()->create([
            'code' => 'pro',
            'name' => ['en' => 'Pro'],
            'monthly_price' => '1500.00',
            'yearly_price' => '15000.00',
            'currency' => 'THB',
            'is_active' => true,
        ]);

        $tenant = Tenant::query()->create([
            'slug' => 'billing-shop',
            'name' => 'Billing Shop',
            'business_type' => TenantBusinessType::Fnb,
            'status' => TenantStatus::Trial,
        ]);

        $subscription = $tenant->subscriptions()->create([
            'plan_id' => $plan->id,
            'billing_cycle' => 'monthly',
            'status' => 'trialing',
        ]);

        $service = app(SubscriptionBillingService::class);
        $first = $service->handlePaymentSucceeded([
            'subscription_id' => $subscription->id,
            'gateway_ref' => 'GW-PAY-0001',
            'amount' => '1500.00',
        ]);
        $second = $service->handlePaymentSucceeded([
            'subscription_id' => $subscription->id,
            'gateway_ref' => 'GW-PAY-0001',
            'amount' => '1500.00',
        ]);

        $this->assertSame($first->id, $second->id);
        $this->assertSame('105.00', (string) $first->vat_amount);
        $this->assertSame('1605.00', (string) $first->total);
        $this->assertSame('active', $subscription->fresh()->status);
        $this->assertSame(TenantStatus::Active, $tenant->fresh()->status);
    }

    public function test_print_content_factory_resolves_label_content_type(): void
    {
        $factory = PrintContentFactory::resolve(PrintContentType::Label);

        $this->assertInstanceOf(PrintLabelContentFactory::class, $factory);
    }
}
