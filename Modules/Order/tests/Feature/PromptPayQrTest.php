<?php

namespace Modules\Order\tests\Feature;

use Modules\Branch\Models\Branch;
use Modules\Order\Enums\OrderPaymentStatus;
use Modules\Order\Enums\OrderStatus;
use Modules\Order\Enums\OrderType;
use Modules\Order\Models\Order;
use Modules\Order\Services\OrderPayment\OrderPaymentServiceInterface;
use Modules\User\Enums\DefaultRole;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\NotificationWorkflowTestCase;

class PromptPayQrTest extends NotificationWorkflowTestCase
{
    public function test_qr_payload_uses_the_branch_promptpay_id_and_due_amount(): void
    {
        $order = $this->createTakeawayOrder('THB', 150.50, '0812345678');

        $result = app(OrderPaymentServiceInterface::class)->getPromptPayQr($order->id);

        $this->assertStringContainsString('01130066812345678', $result['payload']);
        $this->assertStringContainsString('5406150.50', $result['payload']);
        $this->assertSame('THB', $result['currency']);
        $this->assertEqualsWithDelta(150.50, $result['amount']->amount(), 0.000001);
        $this->assertSame($order->id, $result['order_id']);
    }

    public function test_a_partial_amount_generates_a_matching_qr(): void
    {
        $order = $this->createTakeawayOrder('THB', 150.50, '0812345678');

        $result = app(OrderPaymentServiceInterface::class)->getPromptPayQr($order->id, '50.25');

        $this->assertStringContainsString('540550.25', $result['payload']);
        $this->assertEqualsWithDelta(50.25, $result['amount']->amount(), 0.000001);
    }

    public function test_an_amount_above_the_due_balance_is_rejected(): void
    {
        $order = $this->createTakeawayOrder('THB', 150.50, '0812345678');

        try {
            app(OrderPaymentServiceInterface::class)->getPromptPayQr($order->id, '200');
            $this->fail('The QR amount must not exceed the outstanding balance.');
        } catch (HttpException $exception) {
            $this->assertSame(400, $exception->getStatusCode());
        }
    }

    public function test_a_branch_without_promptpay_id_is_rejected(): void
    {
        $order = $this->createTakeawayOrder('THB', 150.50, null);

        try {
            app(OrderPaymentServiceInterface::class)->getPromptPayQr($order->id);
            $this->fail('A branch without PromptPay identity cannot produce a QR.');
        } catch (HttpException $exception) {
            $this->assertSame(400, $exception->getStatusCode());
        }
    }

    public function test_non_thb_orders_are_rejected(): void
    {
        $order = $this->createTakeawayOrder('USD', 150.50, '0812345678');

        try {
            app(OrderPaymentServiceInterface::class)->getPromptPayQr($order->id);
            $this->fail('PromptPay QR must be limited to THB orders.');
        } catch (HttpException $exception) {
            $this->assertSame(400, $exception->getStatusCode());
        }
    }

    private function createTakeawayOrder(string $currency, float $due, ?string $promptPayId): Order
    {
        $branch = Branch::factory()->create([
            'currency' => $currency,
            'payment_methods' => ['promptpay'],
            'promptpay_id' => $promptPayId,
        ]);
        $user = $this->createUserWithRole(DefaultRole::Admin->value, ['branch_id' => null]);
        $this->actingAs($user);

        return Order::query()->create([
            'branch_id' => $branch->id,
            'status' => OrderStatus::Served,
            'type' => OrderType::Takeaway,
            'payment_status' => OrderPaymentStatus::Unpaid,
            'currency' => $currency,
            'currency_rate' => 1,
            'subtotal' => $due,
            'total' => $due,
            'due_amount' => $due,
            'guest_count' => 1,
            'order_date' => now(),
        ]);
    }
}
