<?php

namespace Modules\Order\tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Branch\Models\Branch;
use Modules\Core\Contracts\DeliveryFeatureInterface;
use Modules\Core\Services\Delivery\NullDeliveryFeatureService;
use Modules\Order\Enums\OrderPaymentStatus;
use Modules\Order\Enums\OrderStatus;
use Modules\Order\Enums\OrderType;
use Modules\Order\Models\Order;
use Tests\TestCase;

class OrderEditAllowedTest extends TestCase
{
    use RefreshDatabase;

    public function test_edit_is_allowed_when_delivery_feature_is_not_loaded(): void
    {
        $this->app->forgetInstance(DeliveryFeatureInterface::class);
        $this->app->instance(DeliveryFeatureInterface::class, new NullDeliveryFeatureService);

        $branch = Branch::factory()->create();

        $order = Order::query()->create([
            'branch_id' => $branch->id,
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

        $this->assertTrue($order->editIsAllowed());
    }
}
