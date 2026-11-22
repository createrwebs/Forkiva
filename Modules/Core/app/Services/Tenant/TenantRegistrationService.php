<?php

namespace Modules\Core\Services\Tenant;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Modules\Branch\Models\Branch;
use Modules\Core\Enums\TenantBusinessType;
use Modules\Core\Enums\TenantStatus;
use Modules\Core\Models\Plan;
use Modules\Core\Models\Subscription;
use Modules\Core\Models\Tenant;
use Modules\Core\Support\TenantContext;
use Modules\Order\Enums\OrderType;
use Modules\Payment\Enums\PaymentMethod;
use Modules\User\Models\User;

class TenantRegistrationService
{
    /**
     * Provision a new Thai SME tenant with owner user, main branch, and trial subscription.
     *
     * @param  array<string, mixed>  $data
     * @return array{tenant: Tenant, branch: Branch, owner: User, subscription: Subscription}
     */
    public function register(array $data): array
    {
        return DB::transaction(function () use ($data): array {
            $plan = Plan::query()
                ->where('code', $data['plan_code'] ?? 'starter')
                ->where('is_active', true)
                ->firstOrFail();

            $trialDays = (int) ($data['trial_days'] ?? 14);
            $trialEndsAt = now()->addDays($trialDays);

            $tenant = Tenant::query()->create([
                'slug' => Str::slug((string) ($data['slug'] ?? $data['shop_name'])),
                'name' => (string) $data['shop_name'],
                'business_type' => TenantBusinessType::from((string) ($data['business_type'] ?? TenantBusinessType::Fnb->value)),
                'status' => TenantStatus::Trial,
                'trial_ends_at' => $trialEndsAt,
            ]);

            $subscription = $tenant->subscriptions()->create([
                'plan_id' => $plan->id,
                'billing_cycle' => 'monthly',
                'status' => 'trialing',
                'current_period_start' => now(),
                'current_period_end' => $trialEndsAt,
            ]);

            [$branch, $owner] = TenantContext::runFor($tenant, function () use ($data, $tenant): array {
                $branch = Branch::query()->create([
                    'tenant_id' => $tenant->id,
                    'name' => ['en' => $data['shop_name'], 'th' => $data['shop_name']],
                    'legal_name' => $data['legal_name'] ?? $data['shop_name'],
                    'phone' => $data['phone'] ?? null,
                    'email' => $data['email'],
                    'country_code' => 'TH',
                    'timezone' => 'Asia/Bangkok',
                    'currency' => 'THB',
                    'promptpay_id' => $data['promptpay_id'] ?? null,
                    'tax_branch_code' => $data['tax_branch_code'] ?? '00000',
                    'vat_tin' => $data['vat_tin'] ?? null,
                    'is_main' => true,
                    'is_active' => true,
                    'order_types' => [OrderType::DineIn->value, OrderType::Takeaway->value, OrderType::Delivery->value],
                    'payment_methods' => [PaymentMethod::Cash->value, PaymentMethod::PromptPay->value, PaymentMethod::Card->value],
                ]);

                $owner = User::query()->create([
                    'tenant_id' => $tenant->id,
                    'branch_id' => $branch->id,
                    'name' => (string) $data['owner_name'],
                    'email' => (string) $data['email'],
                    'username' => Str::slug((string) $data['owner_name'], '_').'_'.$tenant->id,
                    'password' => Hash::make((string) $data['password']),
                    'is_active' => true,
                ]);

                return [$branch, $owner];
            });

            return [
                'tenant' => $tenant,
                'branch' => $branch,
                'owner' => $owner,
                'subscription' => $subscription,
            ];
        });
    }
}
