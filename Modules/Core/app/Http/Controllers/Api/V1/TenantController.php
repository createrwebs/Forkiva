<?php

namespace Modules\Core\Http\Controllers\Api\V1;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Core\Enums\TenantBusinessType;
use Modules\Core\Http\Controllers\Controller;
use Modules\Core\Models\Tenant;
use Modules\Core\Services\Tenant\PlanGateService;
use Modules\Core\Services\Tenant\SubscriptionBillingService;
use Modules\Core\Services\Tenant\TenantRegistrationService;
use Modules\Core\Support\TenantContext;
use Modules\Support\ApiResponse;

class TenantController extends Controller
{
    public function __construct(
        private TenantRegistrationService $registrationService,
        private PlanGateService $planGate,
        private SubscriptionBillingService $billingService
    ) {}

    public function register(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'shop_name' => ['required', 'string', 'max:150'],
            'slug' => ['nullable', 'string', 'max:64', 'alpha_dash', 'unique:tenants,slug'],
            'business_type' => ['nullable', 'in:'.implode(',', array_column(TenantBusinessType::cases(), 'value'))],
            'owner_name' => ['required', 'string', 'max:150'],
            'email' => ['required', 'email', 'max:190'],
            'password' => ['required', 'string', 'min:8'],
            'phone' => ['nullable', 'string', 'max:30'],
            'promptpay_id' => ['nullable', 'string', 'max:20'],
            'tax_branch_code' => ['nullable', 'string', 'max:10'],
            'vat_tin' => ['nullable', 'string', 'max:20'],
            'plan_code' => ['nullable', 'string', 'max:40'],
        ]);

        $provisioned = $this->registrationService->register($validated);

        return ApiResponse::created([
            'tenant' => $provisioned['tenant'],
            'branch_id' => $provisioned['branch']->id,
            'owner_id' => $provisioned['owner']->id,
            'subscription' => $provisioned['subscription'],
        ]);
    }

    public function current(): JsonResponse
    {
        $tenant = Tenant::query()
            ->with('activeSubscription.plan')
            ->findOrFail(TenantContext::requireId());

        $plan = $this->planGate->activePlan($tenant);

        return ApiResponse::success([
            'tenant' => $tenant,
            'plan' => $plan,
            'features' => $plan?->features ?? [],
            'limits' => $plan?->limits ?? [],
        ]);
    }

    public function completeOnboarding(Request $request): JsonResponse
    {
        $tenant = Tenant::query()->findOrFail(TenantContext::requireId());
        $tenant->update(['onboarding_completed_at' => now()]);

        return ApiResponse::success($tenant->fresh());
    }

    public function billingWebhook(Request $request): JsonResponse
    {
        $secret = (string) config('services.saas_billing.webhook_secret', '');
        $signature = (string) $request->header('X-Billing-Signature', '');

        if ($secret !== '' && ! $this->billingService->verifyWebhookSignature($request->getContent(), $signature, $secret)) {
            abort(401, 'Invalid billing webhook signature.');
        }

        $validated = $request->validate([
            'subscription_id' => ['required', 'integer', 'exists:subscriptions,id'],
            'gateway_ref' => ['required', 'string', 'max:120'],
            'amount' => ['required', 'numeric', 'gt:0'],
        ]);

        return ApiResponse::success($this->billingService->handlePaymentSucceeded($validated));
    }
}
