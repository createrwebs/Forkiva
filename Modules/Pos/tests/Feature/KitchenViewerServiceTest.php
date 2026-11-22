<?php

namespace Modules\Pos\tests\Feature;

use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Branch\Models\Branch;
use Modules\Core\Contracts\DeliveryFeatureInterface;
use Modules\Core\Services\Delivery\NullDeliveryFeatureService;
use Modules\Menu\Models\Menu;
use Modules\Order\Enums\OrderPaymentStatus;
use Modules\Order\Enums\OrderProductStatus;
use Modules\Order\Enums\OrderStatus;
use Modules\Order\Enums\OrderType;
use Modules\Order\Models\Order;
use Modules\Order\Models\OrderProduct;
use Modules\Order\Transformers\Api\V1\Kitchen\KitchenOrderProductResource;
use Modules\Order\Transformers\Api\V1\Kitchen\KitchenOrderResource;
use Modules\Pos\Services\KitchenViewer\KitchenViewerServiceInterface;
use Modules\Product\Models\Product;
use Modules\User\Models\User;
use Tests\TestCase;

class KitchenViewerServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_order_expected_ready_time_uses_latest_staggered_product_deadline(): void
    {
        $firstStartedAt = Carbon::parse('2026-09-21 13:00:00');
        $secondStartedAt = Carbon::parse('2026-09-21 13:03:00');
        $order = new Order([
            'status' => OrderStatus::Preparing,
            'type' => OrderType::Takeaway,
            'payment_status' => OrderPaymentStatus::Unpaid,
            'preparation_started_at' => $firstStartedAt,
            'created_at' => $firstStartedAt,
        ]);
        $order->setRelation('products', collect([
            new OrderProduct([
                'status' => OrderProductStatus::Preparing,
                'prep_time_minutes' => 10,
                'preparation_started_at' => $firstStartedAt,
            ]),
            new OrderProduct([
                'status' => OrderProductStatus::Preparing,
                'prep_time_minutes' => 15,
                'preparation_started_at' => $secondStartedAt,
            ]),
        ]));

        $payload = (new KitchenOrderResource($order))->resolve();

        $this->assertSame(15, $payload['prep_time_minutes']);
        $this->assertSame($firstStartedAt->toISOString(), $payload['preparation_started_at']);
        $this->assertSame($secondStartedAt->copy()->addMinutes(15)->toISOString(), $payload['expected_ready_at']);
    }

    public function test_orders_load_when_delivery_feature_is_not_enabled(): void
    {
        $this->app->forgetInstance(DeliveryFeatureInterface::class);
        $this->app->instance(DeliveryFeatureInterface::class, new NullDeliveryFeatureService);

        $branch = Branch::factory()->create();
        $user = User::factory()->create();
        $menu = Menu::factory()->create([
            'branch_id' => $branch->id,
            'is_active' => true,
        ]);
        $product = Product::factory()->create([
            'menu_id' => $menu->id,
            'is_active' => true,
        ]);
        $order = Order::query()->create([
            'branch_id' => $branch->id,
            'status' => OrderStatus::Confirmed,
            'customer_id' => $user->id,
            'notes' => 'Keep sauce separate',
            'type' => OrderType::Takeaway,
            'payment_status' => OrderPaymentStatus::Unpaid,
            'currency' => 'JOD',
            'currency_rate' => 1,
            'subtotal' => 10,
            'total' => 10,
            'due_amount' => 10,
            'guest_count' => 1,
            'order_date' => now(),
        ]);

        $orderProduct = OrderProduct::query()->create([
            'order_id' => $order->id,
            'product_id' => $product->id,
            'prep_time_minutes' => 12,
            'created_by' => $user->id,
            'currency' => 'JOD',
            'currency_rate' => 1,
            'unit_price' => 10,
            'quantity' => 1,
            'subtotal' => 10,
            'tax_total' => 0,
            'total' => 10,
            'cost_price' => 0,
            'gross_profit' => 10,
            'status' => OrderProductStatus::Pending,
        ]);

        $this->actingAs($user);

        $configuration = app(KitchenViewerServiceInterface::class)->getConfiguration($branch->id);
        $this->assertTrue($configuration['settings']['served_orders_visibility']['enabled']);

        $data = app(KitchenViewerServiceInterface::class)->getOrders($branch->id);

        $this->assertFalse($data['not_modified']);
        $this->assertCount(1, $data['orders']);
        $this->assertFalse($data['orders']->first()->products->first()->relationLoaded('taxes'));
        $this->assertTrue($data['orders']->first()->products->first()->relationLoaded('product'));
        $this->assertTrue($data['orders']->first()->products->first()->relationLoaded('options'));
        $kitchenOrder = (new KitchenOrderResource($data['orders']->first()))->resolve();
        $this->assertSame(12, $kitchenOrder['prep_time_minutes']);
        $this->assertSame($order->created_at->toISOString(), $kitchenOrder['created_at']);
        $this->assertSame('Keep sauce separate', $kitchenOrder['notes']);
        $this->assertSame(['name' => $user->name], $kitchenOrder['customer']);
        $this->assertNull($kitchenOrder['preparation_started_at']);
        $this->assertNull($kitchenOrder['prepared_at']);
        $this->assertNull($kitchenOrder['expected_ready_at']);
        $kitchenItem = (new KitchenOrderProductResource($data['orders']->first()->products->first()))->resolve();
        $this->assertSame(12, $kitchenItem['prep_time_minutes']);
        $this->assertNull($kitchenItem['preparation_started_at']);
        $this->assertNull($kitchenItem['prepared_at']);
        $this->assertNull($kitchenItem['expected_ready_at']);
        $this->assertSame(150, $data['limit']);
        $this->assertFalse($data['has_more']);

        $searchData = app(KitchenViewerServiceInterface::class)->getOrders($branch->id, null, [
            'search' => $order->order_number,
        ]);
        $this->assertCount(1, $searchData['orders']);

        $typeFilteredData = app(KitchenViewerServiceInterface::class)->getOrders($branch->id, null, [
            'type' => OrderType::DineIn->value,
        ]);
        $this->assertCount(0, $typeFilteredData['orders']);

        $statusFilteredData = app(KitchenViewerServiceInterface::class)->getOrders($branch->id, null, [
            'status' => OrderStatus::Ready->value,
        ]);
        $this->assertCount(0, $statusFilteredData['orders']);

        $limitedData = app(KitchenViewerServiceInterface::class)->getOrders($branch->id, null, [
            'limit' => 1,
        ]);
        $this->assertSame(1, $limitedData['limit']);

        $unchangedData = app(KitchenViewerServiceInterface::class)->getOrders($branch->id, $data['last_updated_at']);

        $this->assertTrue($unchangedData['not_modified']);
        $this->assertArrayNotHasKey('orders', $unchangedData);
        $this->assertSame($data['last_updated_at'], $unchangedData['last_updated_at']);

        $preparingOrder = app(KitchenViewerServiceInterface::class)->moveOrderProductToNextStatus($order->reference_no, [$orderProduct->id]);
        $preparingItem = $preparingOrder->products->first();
        $this->assertSame(OrderProductStatus::Preparing, $preparingItem->status);
        $this->assertNotNull($preparingItem->preparation_started_at);
        $this->assertNotNull($preparingOrder->preparation_started_at);
        $preparingKitchenItem = (new KitchenOrderProductResource($preparingItem))->resolve();
        $this->assertSame(
            $preparingItem->preparation_started_at->copy()->addMinutes(12)->toISOString(),
            $preparingKitchenItem['expected_ready_at']
        );
        $preparingKitchenOrder = (new KitchenOrderResource($preparingOrder))->resolve();
        $this->assertSame(12, $preparingKitchenOrder['prep_time_minutes']);
        $this->assertNotNull($preparingKitchenOrder['preparation_started_at']);
        $this->assertSame($preparingKitchenItem['expected_ready_at'], $preparingKitchenOrder['expected_ready_at']);

        $this->travel(1)->minute();
        $readyOrder = app(KitchenViewerServiceInterface::class)->moveOrderProductToNextStatus($order->reference_no, [$orderProduct->id]);
        $readyItem = $readyOrder->products->first();
        $this->assertSame(OrderProductStatus::Ready, $readyItem->status);
        $this->assertNotNull($readyItem->prepared_at);
        $this->assertNotNull($readyOrder->prepared_at);

        setting(['kitchen_show_served_orders_enabled' => false]);
        $this->app->forgetInstance('setting');
        $configuration = app(KitchenViewerServiceInterface::class)->getConfiguration($branch->id);
        $this->assertFalse($configuration['settings']['served_orders_visibility']['enabled']);
        $hiddenData = app(KitchenViewerServiceInterface::class)->getOrders($branch->id);
        $this->assertCount(0, $hiddenData['orders']);

        setting(['kitchen_show_served_orders_enabled' => true, 'kitchen_show_served_orders_minutes' => 5]);
        $this->app->forgetInstance('setting');
        $configuration = app(KitchenViewerServiceInterface::class)->getConfiguration($branch->id);
        $this->assertSame(['enabled' => true, 'minutes' => 5], $configuration['settings']['served_orders_visibility']);

        $changedData = app(KitchenViewerServiceInterface::class)->getOrders($branch->id, $data['last_updated_at']);

        $this->assertFalse($changedData['not_modified']);
        $this->assertCount(1, $changedData['orders']);

        $returnedOrder = app(KitchenViewerServiceInterface::class)->moveOrderProductToNextStatus($order->reference_no, [$orderProduct->id]);

        $this->assertSame($order->id, $returnedOrder->id);
        $this->assertTrue($returnedOrder->relationLoaded('products'));
        $this->assertSame(OrderProductStatus::Served, $returnedOrder->products->first()->status);

        $visibleData = app(KitchenViewerServiceInterface::class)->getOrders($branch->id);
        $this->assertCount(1, $visibleData['orders']);
        $this->travel(6)->minutes();
        $expiredData = app(KitchenViewerServiceInterface::class)->getOrders($branch->id);
        $this->assertCount(0, $expiredData['orders']);
    }
}
