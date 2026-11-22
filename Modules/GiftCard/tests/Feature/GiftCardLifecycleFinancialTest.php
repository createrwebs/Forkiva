<?php

namespace Modules\GiftCard\Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Modules\Branch\Models\Branch;
use Modules\Core\Contracts\FinancialAccountLookupInterface;
use Modules\GiftCard\Enums\GiftCardBreakageBatchStatus;
use Modules\GiftCard\Enums\GiftCardFundingType;
use Modules\GiftCard\Enums\GiftCardStatus;
use Modules\GiftCard\Enums\GiftCardTransactionType;
use Modules\GiftCard\Exceptions\InsufficientGiftCardBalanceException;
use Modules\GiftCard\Models\GiftCard;
use Modules\GiftCard\Services\GiftCard\GiftCardServiceInterface;
use Modules\GiftCard\Services\GiftCardBreakage\GiftCardBreakageServiceInterface;
use Modules\GiftCard\Services\GiftCardTransaction\GiftCardTransactionServiceInterface;
use Modules\Order\Enums\OrderPaymentStatus;
use Modules\Order\Enums\OrderStatus;
use Modules\Order\Enums\OrderType;
use Modules\Order\Models\Order;
use Modules\Payment\Enums\PaymentMethod;
use Modules\Payment\Enums\PaymentType;
use Modules\Payment\Models\Payment;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

class GiftCardLifecycleFinancialTest extends TestCase
{
    use RefreshDatabase;

    public function test_paid_and_promotional_financial_actions_freeze_their_funding_and_account_snapshots(): void
    {
        $branch = Branch::factory()->create(['currency' => 'JOD']);
        $this->useAvailableFinancialAccounts();
        $card = app(GiftCardServiceInterface::class)->store([
            'branch_id' => $branch->id,
            'initial_balance' => '100.0000',
            'status' => GiftCardStatus::Active->value,
            'funding_type' => GiftCardFundingType::Paid->value,
            'tender_method' => PaymentMethod::Cash->value,
            'financial_account_key' => 'cash-main',
            'idempotency_key' => 'paid-issue-lifecycle',
            'economic_date' => '2026-07-22',
        ]);
        $service = app(GiftCardTransactionServiceInterface::class);
        $issue = $card->transactions()->firstOrFail();
        $this->assertSame(GiftCardFundingType::Paid->value, $issue->funding_type);
        $this->assertSame('cash-main', $issue->financial_account_key);
        $this->assertSame('1.00000000', $issue->currency_rate);

        $promotional = $service->recordFinancialAction($card->id, [
            'type' => GiftCardTransactionType::Recharge,
            'amount' => '10.0000',
            'funding_type' => GiftCardFundingType::Promotional->value,
            'economic_date' => '2026-07-22',
            'idempotency_key' => 'promotional-recharge-lifecycle',
            'notes' => 'Approved promotion.',
        ]);
        $paid = $service->recordFinancialAction($card->id, [
            'type' => GiftCardTransactionType::Recharge,
            'amount' => '20.0000',
            'funding_type' => GiftCardFundingType::Paid->value,
            'tender_method' => PaymentMethod::Card->value,
            'financial_account_key' => 'card-main',
            'economic_date' => '2026-07-22',
            'idempotency_key' => 'paid-recharge-lifecycle',
            'notes' => 'Customer recharge.',
        ]);
        $cashOut = $service->recordFinancialAction($card->id, [
            'type' => GiftCardTransactionType::CashOut,
            'amount' => '5.0000',
            'tender_method' => PaymentMethod::Cash->value,
            'financial_account_key' => 'cash-main',
            'economic_date' => '2026-07-22',
            'idempotency_key' => 'cash-out-lifecycle',
            'notes' => 'Approved cash-out.',
        ]);

        $this->assertSame(GiftCardFundingType::Promotional->value, $promotional->funding_type);
        $this->assertNull($promotional->financial_account_snapshot);
        $this->assertSame('card-main', $paid->financial_account_snapshot['key']);
        $this->assertSame('cash-main', $cashOut->financial_account_snapshot['key']);
        $this->assertEquals(125, $card->fresh()->getRawOriginal('current_balance'));
    }

    private function useAvailableFinancialAccounts(): void
    {
        $this->app->instance(FinancialAccountLookupInterface::class, new class implements FinancialAccountLookupInterface
        {
            public function available(): bool
            {
                return true;
            }

            public function list(int $branchId, ?string $currency = null): array
            {
                return [];
            }

            public function resolve(string $key, int $branchId, string $currency): ?array
            {
                return [
                    'key' => $key,
                    'label' => strtoupper($key),
                    'branch_id' => $branchId,
                    'currency' => $currency,
                    'active' => true,
                ];
            }
        });
    }

    public function test_adjustments_and_generic_eligible_reversal_restore_the_exact_balance(): void
    {
        $branch = Branch::factory()->create(['currency' => 'JOD']);
        $card = GiftCard::factory()->branchScoped($branch->id)->withBalance(50, 50)->create();
        $service = app(GiftCardTransactionServiceInterface::class);
        $increase = $service->record($card, [
            'type' => GiftCardTransactionType::Adjustment,
            'amount' => '7.2500',
            'economic_date' => '2026-07-22',
            'idempotency_key' => 'adjust-up-lifecycle',
            'notes' => 'Approved increase.',
        ]);
        $service->record($card, [
            'type' => GiftCardTransactionType::AdjustmentDecrease,
            'amount' => '2.1250',
            'economic_date' => '2026-07-22',
            'idempotency_key' => 'adjust-down-lifecycle',
            'notes' => 'Approved decrease.',
        ]);
        $service->reverse($increase->id, [
            'economic_date' => '2026-07-22',
            'idempotency_key' => 'adjust-up-reversal-lifecycle',
            'reason' => 'Correction approved.',
        ]);

        $this->assertEquals(47.875, $card->fresh()->getRawOriginal('current_balance'));
        $this->assertNotNull($increase->fresh()->reversed_at);
    }

    public function test_payment_owned_refund_reversal_rolls_back_when_later_spending_consumed_the_balance(): void
    {
        $branch = Branch::factory()->create(['currency' => 'JOD']);
        [$order, $payment] = $this->createGiftCardRefundPayment($branch);
        $card = GiftCard::factory()->branchScoped($branch->id)->withBalance(10, 10)->create();
        $payment->update(['meta' => ['gift_card_id' => $card->id]]);
        $service = app(GiftCardTransactionServiceInterface::class);
        $refund = $service->record($card, [
            'type' => GiftCardTransactionType::Refund,
            'amount' => '20.0000',
            'payment_id' => $payment->id,
            'order_id' => $order->id,
            'branch_id' => $branch->id,
            'economic_date' => '2026-07-22',
            'idempotency_key' => 'refund-lifecycle',
        ]);
        $service->record($card, [
            'type' => GiftCardTransactionType::Redeem,
            'amount' => '25.0000',
            'branch_id' => $branch->id,
            'economic_date' => '2026-07-22',
            'idempotency_key' => 'later-spend-lifecycle',
        ]);

        try {
            $service->reversePaymentTransaction($payment->id, [
                'economic_date' => '2026-07-22',
                'idempotency_key' => 'refund-reversal-lifecycle',
                'reason' => 'Payment reversal.',
            ]);
            $this->fail('The refund reversal should fail after its balance was spent.');
        } catch (InsufficientGiftCardBalanceException) {
            $this->assertEquals(5, $card->fresh()->getRawOriginal('current_balance'));
            $this->assertNull($refund->fresh()->reversed_at);
        }

        $this->expectException(HttpException::class);
        $service->reverse($refund->id, [
            'economic_date' => '2026-07-22',
            'idempotency_key' => 'invalid-generic-refund-reversal',
            'reason' => 'Invalid owner.',
        ]);
    }

    private function createGiftCardRefundPayment(Branch $branch): array
    {
        $order = Order::query()->create([
            'branch_id' => $branch->id,
            'status' => OrderStatus::Completed,
            'type' => OrderType::Takeaway,
            'payment_status' => OrderPaymentStatus::Paid,
            'currency' => $branch->currency,
            'currency_rate' => 1,
            'subtotal' => 20,
            'total' => 20,
            'due_amount' => 0,
            'guest_count' => 1,
            'order_date' => now(),
        ]);
        $payment = Payment::query()->create([
            'order_id' => $order->id,
            'order_reference_no' => $order->reference_no,
            'branch_id' => $branch->id,
            'transaction_id' => 'GIFT-REFUND-LIFECYCLE',
            'method' => PaymentMethod::GiftCard,
            'type' => PaymentType::Refund,
            'amount' => 20,
            'currency' => $branch->currency,
            'currency_rate' => 1,
            'received_at' => now(),
        ]);

        return [$order, $payment];
    }

    public function test_expiry_breakage_and_batch_reversal_preserve_the_reviewed_rate_and_balance(): void
    {
        $branch = Branch::factory()->create(['currency' => 'JOD']);
        $card = GiftCard::factory()->branchScoped($branch->id)->withBalance(15, 15)->create([
            'status' => GiftCardStatus::Active,
            'expiry_date' => now()->subDay(),
        ]);
        app(GiftCardTransactionServiceInterface::class)->expireEligibleCards();
        $this->assertSame(GiftCardStatus::Expired, $card->fresh()->status);
        $batchService = app(GiftCardBreakageServiceInterface::class);
        $batch = $batchService->store([
            'branch_id' => $branch->id,
            'gift_card_ids' => [$card->id],
            'economic_date' => '2026-07-22',
            'idempotency_key' => 'breakage-lifecycle',
            'review_note' => 'Reviewed expired balance.',
        ]);
        $batch = $batchService->review($batch->id, [
            'economic_date' => '2026-07-22',
            'review_note' => 'Reviewed expired balance.',
        ]);
        $posted = $batchService->post($batch->id);
        $reversed = $batchService->reverse($posted->id, [
            'economic_date' => '2026-07-22',
            'idempotency_key' => 'breakage-reversal-lifecycle',
            'reason' => 'Approved correction.',
        ]);

        $postedLine = $posted->lines->firstOrFail();
        $reversalLine = $reversed->lines->firstOrFail();
        $this->assertSame($posted->currency_rate, $reversed->currency_rate);
        $this->assertSame($posted->currency, $postedLine->currency);
        $this->assertSame($posted->currency_rate, $postedLine->currency_rate);
        $this->assertSame($postedLine->currency, $reversalLine->currency);
        $this->assertSame($postedLine->currency_rate, $reversalLine->currency_rate);
        $this->assertEquals(
            15,
            $card->fresh()->getRawOriginal('current_balance'),
            $card->transactions()->get(['type', 'currency', 'amount', 'balance_before', 'balance_after'])->toJson(),
        );
        $this->assertSame(GiftCardBreakageBatchStatus::Reversed, $posted->fresh()->status);
    }

    public function test_cross_currency_card_uses_card_to_base_rate_and_reconciles_base_value(): void
    {
        DB::table('currency_rates')->updateOrInsert(['currency' => 'USD'], ['rate' => 1.41]);
        Cache::flush();
        $branch = Branch::factory()->create(['currency' => 'USD']);
        $card = app(GiftCardServiceInterface::class)->store([
            'branch_id' => $branch->id,
            'initial_balance' => '14.1000',
            'status' => GiftCardStatus::Active->value,
            'funding_type' => GiftCardFundingType::Promotional->value,
            'idempotency_key' => 'cross-currency-issue',
            'economic_date' => '2026-07-22',
        ]);
        $issue = $card->transactions()->firstOrFail();
        $this->assertSame('0.70921986', $issue->currency_rate);

        $converted = app(GiftCardServiceInterface::class)->convertOrderAmountToGiftCardAmount(
            $card,
            '10.0000',
            (string) setting('default_currency', 'JOD'),
            1,
            $issue->currency_rate,
        );
        $this->assertEquals(14.10, $converted->amount());
        $this->assertEqualsWithDelta(10.0, 14.1 * (float) $issue->currency_rate, 0.000001);
    }
}
