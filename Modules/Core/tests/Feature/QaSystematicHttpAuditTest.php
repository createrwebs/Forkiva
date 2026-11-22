<?php

namespace Modules\Core\Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Branch\Models\Branch;
use Modules\Core\Models\Plan;
use Modules\Core\Models\Tenant;
use Modules\Payment\Support\ThaiSlipQrParser;
use Modules\User\Models\User;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

/**
 * Systematic QA HTTP Audit covering:
 * 1. Smoke & Critical Path (Public registration -> Tenant context -> APIs)
 * 2. Functional & Validation (Empty/Null, Negative numbers, Invalid QR, Double Submit)
 * 3. Auth, Authorization & Basic Security (Unauthenticated direct access, Cross-branch access, XSS payload, No stack trace leak)
 * 4. Data Persistence across requests
 */
class QaSystematicHttpAuditTest extends TestCase
{
    use RefreshDatabase;

    public function test_unauthenticated_direct_access_to_protected_endpoints_is_blocked(): void
    {
        $protectedEndpoints = [
            ['GET', '/api/v1/tenant/current'],
            ['POST', '/api/v1/payments/slip-verify'],
            ['GET', '/api/v1/pos/hold-carts?branch_id=1'],
            ['POST', '/api/v1/pos/offline-sync'],
            ['POST', '/api/v1/reports/accounting-export'],
            ['POST', '/api/v1/invoices/wht-calculate'],
            ['POST', '/api/v1/seating-plan/buffet/evaluate'],
        ];

        foreach ($protectedEndpoints as [$method, $uri]) {
            $response = $this->json($method, $uri);
            $this->assertContains(
                $response->status(),
                [401, 403],
                "Endpoint {$method} {$uri} must reject unauthenticated access, got {$response->status()}."
            );
        }
    }

    public function test_public_tenant_registration_validation_xss_and_persistence(): void
    {
        Plan::query()->create([
            'code' => 'starter',
            'name' => ['en' => 'Starter', 'th' => 'เริ่มต้น'],
            'monthly_price' => '790.00',
            'yearly_price' => '7900.00',
            'currency' => 'THB',
            'limits' => ['max_branches' => 1],
            'features' => ['slip_verify'],
            'is_active' => true,
        ]);

        // Empty payload -> 422 validation error, no 500 or stack trace
        $emptyResp = $this->postJson('/api/v1/public/register-tenant', []);
        $emptyResp->assertStatus(422);
        $this->assertArrayNotHasKey('trace', $emptyResp->json());

        // XSS probe in shop_name -> rejected with 422
        $xssResp = $this->postJson('/api/v1/public/register-tenant', [
            'shop_name' => 'qa_test_<script>alert(1)</script>',
            'slug' => 'qa-xss-shop',
            'business_type' => 'fnb',
            'owner_name' => 'QA Owner',
            'email' => 'qa_xss@example.com',
            'password' => 'Secret1234!',
            'plan_code' => 'starter',
        ]);
        $xssResp->assertStatus(422);

        // Happy path registration
        $registerResp = $this->postJson('/api/v1/public/register-tenant', [
            'shop_name' => 'QA Bistro Thailand',
            'slug' => 'qa-test-shop',
            'business_type' => 'fnb',
            'owner_name' => 'QA Owner',
            'email' => 'qa_owner@example.com',
            'password' => 'Secret1234!',
            'promptpay_id' => '0812345678',
            'plan_code' => 'starter',
        ]);

        $registerResp->assertStatus(201);
        $this->assertDatabaseHas('tenants', ['slug' => 'qa-test-shop']);

        // Double submit with same slug -> 422 unique validation
        $doubleResp = $this->postJson('/api/v1/public/register-tenant', [
            'shop_name' => 'qa_test_dup',
            'slug' => 'qa-test-shop',
            'owner_name' => 'QA Owner',
            'email' => 'qa_owner2@example.com',
            'password' => 'Secret1234!',
        ]);
        $doubleResp->assertStatus(422);
    }

    public function test_slip_verify_blocks_cross_branch_user_and_double_submit(): void
    {
        $branchA = Branch::factory()->create(['promptpay_id' => '0812345678', 'currency' => 'THB']);
        $branchB = Branch::factory()->create(['promptpay_id' => '0899998888', 'currency' => 'THB']);

        $staffA = User::factory()->create(['branch_id' => $branchA->id]);
        $perm = Permission::findOrCreate('admin.orders.receive_payment', 'api');
        $staffA->givePermissionTo($perm);

        $qr = ThaiSlipQrParser::build('014', 'QA202611230001');

        // Cross-branch attempt: Staff A tries to verify slip on Branch B -> 403
        $crossResp = $this->actingAs($staffA)->postJson('/api/v1/payments/slip-verify', [
            'branch_id' => $branchB->id,
            'qr_payload' => $qr,
            'expected_amount' => '150.00',
        ]);
        $crossResp->assertStatus(403);

        // Negative amount edge case -> 422
        $negResp = $this->actingAs($staffA)->postJson('/api/v1/payments/slip-verify', [
            'branch_id' => $branchA->id,
            'qr_payload' => $qr,
            'expected_amount' => '-50.00',
        ]);
        $negResp->assertStatus(422);

        // Valid verification on Branch A -> 201
        $okResp = $this->actingAs($staffA)->postJson('/api/v1/payments/slip-verify', [
            'branch_id' => $branchA->id,
            'qr_payload' => $qr,
            'expected_amount' => '150.00',
        ]);
        $okResp->assertStatus(201);

        // Double submit / Replay attack -> 409
        $replayResp = $this->actingAs($staffA)->postJson('/api/v1/payments/slip-verify', [
            'branch_id' => $branchA->id,
            'qr_payload' => $qr,
            'expected_amount' => '150.00',
        ]);
        $replayResp->assertStatus(409);
    }

    public function test_retail_hold_cart_and_offline_sync_http_persistence_and_idempotency(): void
    {
        $branch = Branch::factory()->create(['currency' => 'THB']);
        $cashier = User::factory()->create(['branch_id' => $branch->id]);
        $perm = Permission::findOrCreate('admin.pos.index', 'api');
        $cashier->givePermissionTo($perm);

        // Hold cart -> List -> Resume
        $holdResp = $this->actingAs($cashier)->postJson('/api/v1/pos/hold-carts', [
            'branch_id' => $branch->id,
            'label' => 'qa_test_table_wait',
            'items' => [['product_id' => 1, 'qty' => 2]],
        ]);
        $holdResp->assertStatus(201);
        $heldId = $holdResp->json('body.id');

        $listResp = $this->actingAs($cashier)->getJson("/api/v1/pos/hold-carts?branch_id={$branch->id}");
        $listResp->assertStatus(200);
        $this->assertCount(1, $listResp->json('body'));

        $resumeResp = $this->actingAs($cashier)->postJson("/api/v1/pos/hold-carts/{$heldId}/resume");
        $resumeResp->assertStatus(200);

        // Offline sync double submit -> idempotent
        $syncPayload = [
            'branch_id' => $branch->id,
            'orders' => [
                [
                    'client_uuid' => '550e8400-e29b-41d4-a716-446655440099',
                    'offline_reference' => 'OFF-QA-001',
                    'payment_method' => 'cash',
                    'total' => '99.00',
                    'items' => [['product_id' => 1, 'quantity' => 1, 'unit_price' => '99.00']],
                ],
            ],
        ];

        $sync1 = $this->actingAs($cashier)->postJson('/api/v1/pos/offline-sync', $syncPayload);
        $sync1->assertStatus(200);
        $this->assertSame('synced', $sync1->json('body.results.0.status'));

        $sync2 = $this->actingAs($cashier)->postJson('/api/v1/pos/offline-sync', $syncPayload);
        $sync2->assertStatus(200);
        $this->assertSame('duplicate', $sync2->json('body.results.0.status'));
    }

    public function test_webhooks_reject_invalid_hmac_signatures(): void
    {
        config()->set('services.saas_billing.webhook_secret', 'billing-secret-qa');
        config()->set('services.delivery_aggregators.grabfood.webhook_secret', 'grab-secret-qa');

        $billingResp = $this->postJson('/api/v1/webhooks/subscription-billing', [
            'subscription_id' => 1,
            'gateway_ref' => 'REF-1',
            'amount' => '790.00',
        ], ['X-Billing-Signature' => 'invalid-sig']);
        $billingResp->assertStatus(401);

        $grabResp = $this->postJson('/api/v1/webhooks/delivery/grabfood', [
            'orderID' => 'GF-1',
        ], ['X-Webhook-Signature' => 'invalid-sig']);
        $grabResp->assertStatus(401);
    }

    public function test_suspended_tenant_is_blocked_at_middleware(): void
    {
        $tenant = Tenant::query()->create([
            'slug' => 'qa-suspended',
            'name' => 'Suspended Shop',
            'business_type' => 'fnb',
            'status' => 'suspended',
        ]);
        $user = User::factory()->create(['tenant_id' => $tenant->id]);

        $resp = $this->actingAs($user)
            ->withHeader('X-Tenant-Slug', 'qa-suspended')
            ->getJson('/api/v1/tenant/current');

        $resp->assertStatus(403);
    }

    public function test_unhandled_api_error_does_not_leak_stack_trace_when_debug_is_false(): void
    {
        config()->set('app.debug', false);

        \Illuminate\Support\Facades\Route::get('/api/v1/qa-force-500', function (): void {
            throw new \RuntimeException('Sensitive SQL credential leak test');
        });

        $response = $this->getJson('/api/v1/qa-force-500');

        $response->assertStatus(500);
        $this->assertArrayNotHasKey('trace', $response->json());
        $this->assertArrayNotHasKey('exception', $response->json());
        $this->assertStringNotContainsString('Sensitive SQL', $response->getContent());
    }

    public function test_root_url_redirects_to_login_and_web_shell_includes_thai_loader(): void
    {
        $this->get('/')->assertRedirect('/auth/login');

        $loginShell = $this->get('/auth/login');
        $loginShell->assertStatus(200);
        $loginShell->assertSee('kitchen-loader__copy-th', false);
        $loginShell->assertSee('กำลังเตรียมทุกอย่างให้คุณ', false);
    }

    public function test_retail_pos_blocks_cross_branch_idor_on_hold_cart_and_offline_sync(): void
    {
        $branchA = Branch::factory()->create(['currency' => 'THB']);
        $branchB = Branch::factory()->create(['currency' => 'THB']);

        $cashierA = User::factory()->create(['branch_id' => $branchA->id]);
        $cashierA->givePermissionTo(Permission::findOrCreate('admin.pos.index', 'api'));

        // Cashier A cannot list or hold cart for Branch B
        $this->actingAs($cashierA)->getJson("/api/v1/pos/hold-carts?branch_id={$branchB->id}")->assertStatus(403);
        $this->actingAs($cashierA)->postJson('/api/v1/pos/hold-carts', [
            'branch_id' => $branchB->id,
            'label' => 'idor-attempt',
            'items' => [['product_id' => 1, 'qty' => 1]],
        ])->assertStatus(403);

        // Cashier A cannot push offline sync into Branch B
        $this->actingAs($cashierA)->postJson('/api/v1/pos/offline-sync', [
            'branch_id' => $branchB->id,
            'orders' => [
                [
                    'client_uuid' => '550e8400-e29b-41d4-a716-446655440777',
                    'offline_reference' => 'OFF-IDOR-001',
                    'payment_method' => 'cash',
                    'total' => '50.00',
                    'items' => [['product_id' => 1, 'quantity' => 1, 'unit_price' => '50.00']],
                ],
            ],
        ])->assertStatus(403);
    }
}
