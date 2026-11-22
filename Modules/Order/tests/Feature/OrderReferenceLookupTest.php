<?php

namespace Modules\Order\tests\Feature;

use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Branch\Models\Branch;
use Modules\Order\Enums\OrderPaymentStatus;
use Modules\Order\Enums\OrderStatus;
use Modules\Order\Enums\OrderType;
use Modules\Order\Models\Order;
use Modules\Order\Services\Order\OrderServiceInterface;
use Modules\Order\Services\OrderPayment\OrderPaymentServiceInterface;
use Tests\TestCase;

class OrderReferenceLookupTest extends TestCase
{
    use RefreshDatabase;

    public function test_payment_metadata_accepts_references_and_numeric_ids(): void
    {
        $order = $this->createOrder();
        $payments = app(OrderPaymentServiceInterface::class);
        $expected = $payments->getPaymentMeta($order->id);

        $this->assertEquals($expected, $payments->getPaymentMeta($order->reference_no));
        $this->assertEquals($expected, $payments->getPaymentMeta((string) $order->id));
        $this->assertSame(10.0, $expected['order']['due_amount']->amount());
    }

    public function test_order_lookup_accepts_references_and_preserves_other_filters(): void
    {
        $order = $this->createOrder();
        $orders = app(OrderServiceInterface::class);

        foreach ([$order->id, (string) $order->id, $order->reference_no] as $identifier) {
            $this->assertSame($order->id, $orders->findOrFail($identifier)->id);
            $this->assertFalse(Order::query()->where('branch_id', -1)->whereIdOrReference($identifier)->exists());
        }
    }

    public function test_invalid_identifiers_return_not_found_instead_of_database_errors(): void
    {
        $this->createOrder();
        foreach (['ORD-MISSING', '999999999999999999999999999', '1.5', '1e2'] as $identifier) {
            try {
                app(OrderPaymentServiceInterface::class)->getPaymentMeta($identifier);
                $this->fail('An unknown identifier must not resolve to an order.');
            } catch (ModelNotFoundException) {
                $this->addToAssertionCount(1);
            }
        }
    }

    private function createOrder(): Order
    {
        return Order::query()->create([
            'branch_id' => Branch::factory()->create()->id,
            'status' => OrderStatus::Ready,
            'type' => OrderType::Delivery,
            'payment_status' => OrderPaymentStatus::Unpaid,
            'currency' => 'JOD',
            'currency_rate' => 1,
            'subtotal' => 10,
            'total' => 10,
            'due_amount' => 10,
            'guest_count' => 1,
            'order_date' => now(),
        ]);
    }
}
