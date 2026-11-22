<?php

namespace Modules\Core\Services\Tenant;

use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;
use Illuminate\Support\Facades\DB;
use Modules\Core\Enums\TenantStatus;
use Modules\Core\Models\Subscription;
use Modules\Core\Models\SubscriptionInvoice;

class SubscriptionBillingService
{
    public function verifyWebhookSignature(string $rawBody, string $signature, string $secret): bool
    {
        if ($signature === '' || $secret === '') {
            return false;
        }

        return hash_equals(hash_hmac('sha256', $rawBody, $secret), $signature);
    }

    /**
     * Record a paid subscription invoice idempotently by gateway_ref and renew the tenant subscription.
     *
     * @param  array<string, mixed>  $payload
     */
    public function handlePaymentSucceeded(array $payload): SubscriptionInvoice
    {
        return DB::transaction(function () use ($payload): SubscriptionInvoice {
            $gatewayRef = (string) $payload['gateway_ref'];

            $existing = SubscriptionInvoice::query()->where('gateway_ref', $gatewayRef)->first();
            if ($existing !== null) {
                return $existing;
            }

            $subscription = Subscription::query()->with('tenant')->lockForUpdate()->findOrFail((int) $payload['subscription_id']);
            $amount = BigDecimal::of((string) $payload['amount'])->toScale(2, RoundingMode::HALF_UP);
            $vatAmount = $amount->multipliedBy('0.07')->toScale(2, RoundingMode::HALF_UP);
            $total = $amount->plus($vatAmount)->toScale(2, RoundingMode::HALF_UP);

            $invoice = SubscriptionInvoice::query()->create([
                'tenant_id' => $subscription->tenant_id,
                'subscription_id' => $subscription->id,
                'invoice_number' => 'SUB-'.now()->format('Ym').'-'.str_pad((string) random_int(1, 99999), 5, '0', STR_PAD_LEFT),
                'amount' => (string) $amount,
                'vat_amount' => (string) $vatAmount,
                'total' => (string) $total,
                'status' => 'paid',
                'gateway_ref' => $gatewayRef,
                'paid_at' => now(),
            ]);

            $periodStart = now();
            $periodEnd = $subscription->billing_cycle === 'yearly'
                ? $periodStart->copy()->addYear()
                : $periodStart->copy()->addMonth();

            $subscription->update([
                'status' => 'active',
                'current_period_start' => $periodStart,
                'current_period_end' => $periodEnd,
                'grace_period_ends_at' => null,
            ]);

            $subscription->tenant->update(['status' => TenantStatus::Active]);

            return $invoice;
        });
    }
}
