<?php

namespace Modules\Pos\Tests\Feature;

use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;
use Modules\Branch\Models\Branch;
use Modules\Menu\Models\Menu;
use Modules\Option\Enums\OptionType;
use Modules\Option\Models\Option;
use Modules\Option\Models\OptionValue;
use Modules\Order\Enums\OrderPaymentStatus;
use Modules\Order\Enums\OrderStatus;
use Modules\Order\Http\Controllers\Api\V1\OrderController;
use Modules\Order\Models\Order;
use Modules\Pos\Models\PosRegister;
use Modules\Pos\Models\PosSession;
use Modules\Pos\Services\KitchenViewer\KitchenViewerServiceInterface;
use Modules\Printer\Factories\OrderResourceFactory;
use Modules\Product\Models\Product;
use Modules\Support\Enums\PriceType;
use Modules\Tax\Enums\TaxType;
use Modules\Tax\Models\Tax;
use Modules\User\Enums\DefaultRole;
use Tests\NotificationWorkflowTestCase;

class PosCartCheckoutFlowTest extends NotificationWorkflowTestCase
{
    public function test_cart_to_kitchen_payment_invoice_and_print_data(): void
    {
        app()->setLocale('en');
        Notification::fake();
        $branch = Branch::factory()->create(['currency' => 'USD', 'is_active' => true, 'order_types' => ['takeaway'], 'payment_methods' => ['card']]);
        $user = $this->createUserWithRole(DefaultRole::Admin->value, ['branch_id' => null]);
        $this->actingAs($user);
        $menu = Menu::factory()->create(['branch_id' => $branch->id, 'is_active' => true]);
        $register = PosRegister::factory()->create(['branch_id' => $branch->id, 'is_active' => true]);
        $session = PosSession::factory()->create(['branch_id' => $branch->id, 'pos_register_id' => $register->id, 'opened_by' => $user->id, 'opened_at' => now()]);
        $product = Product::factory()->create(['menu_id' => $menu->id, 'name' => ['en' => 'Soup'], 'price' => 10, 'special_price' => null, 'is_active' => true]);
        $option = Option::factory()->create(['name' => ['en' => 'Size'], 'type' => OptionType::Select, 'is_required' => true, 'branch_id' => $branch->id]);
        $value = OptionValue::factory()->create(['option_id' => $option->id, 'branch_id' => $branch->id, 'label' => ['en' => 'Large'], 'price' => 2, 'price_type' => PriceType::Fixed]);
        $product->options()->attach($option);
        Tax::create(['name' => ['en' => 'Tax'], 'code' => 'CHECKOUT_TAX', 'rate' => 10, 'type' => TaxType::Exclusive, 'compound' => false, 'is_global' => true, 'branch_id' => $branch->id, 'order_types' => [], 'is_active' => true]);
        Route::post('/test-checkout/quote', [OrderController::class, 'quote']);
        Route::post('/test-checkout/orders', [OrderController::class, 'store']);
        Route::post('/test-checkout/orders/{orderId}/payment', [OrderController::class, 'storePayment']);
        $payload = ['branch_id' => $branch->id, 'menu_id' => $menu->id, 'register_id' => $register->id, 'session_id' => $session->id, 'submit_action' => 'send_to_kitchen', 'type' => 'takeaway', 'items' => [
            ['line_id' => 'soup', 'product_id' => $product->id, 'qty' => 2, 'options' => [$option->id => $value->id]],
        ]];
        $this->postJson('/test-checkout/quote', [...$payload, 'total' => 0.01])->assertUnprocessable();
        $invalid = $payload;
        $invalid['items'][0]['options'] = [];
        $this->postJson('/test-checkout/quote', $invalid)->assertUnprocessable();
        $quote = $this->postJson('/test-checkout/quote', $payload)->assertOk();
        $this->assertEqualsWithDelta(26.4, $quote->json('body.pricing.total.amount'), 0.001);
        $payload['quote_token'] = $quote->json('body.quote_token');
        $payload['idempotency_key'] = (string) Str::uuid();
        $response = $this->postJson('/test-checkout/orders', $payload)->assertOk();
        $replay = $this->postJson('/test-checkout/orders', $payload)->assertOk();
        $this->assertSame($response->json(), $replay->json());
        $this->travel(6)->minutes();
        $session->update(['status' => 'closed']);
        $this->postJson('/test-checkout/orders', $payload)->assertOk()->assertExactJson($response->json());
        $changed = $payload;
        $changed['items'][0]['qty'] = 3;
        $this->postJson('/test-checkout/orders', $changed)->assertConflict();
        $session->update(['status' => 'open']);
        $this->travelBack();

        $this->assertDatabaseCount('orders', 1);
        $order = Order::firstOrFail();
        $this->assertSame(OrderStatus::Confirmed, $order->status);
        $this->assertEquals(2, $order->products()->sum('quantity'));
        $this->assertEqualsWithDelta(26.4, $order->total->amount(), 0.001);

        $kitchen = app(KitchenViewerServiceInterface::class)->getOrders($branch->id);
        $this->assertTrue($kitchen['orders']->contains('id', $order->id));
        $ticket = OrderResourceFactory::product($order->products->first(), true);
        $this->assertSame('Soup', $ticket['name']);
        $this->assertEquals(2, $ticket['quantity']);
        $this->assertSame('Large', $ticket['options']->first()['values']->first()['label']);

        $this->postJson('/test-checkout/orders/'.$order->reference_no.'/payment', [
            'session_id' => $session->id,
            'register_id' => $register->id,
            'payment_mode' => 'full',
            'payments' => [['method' => 'card', 'amount' => 26.4]],
            'with_print' => false,
        ])->assertOk();
        $order->refresh();
        $this->assertSame(OrderPaymentStatus::Paid, $order->payment_status);
        $this->assertEquals(0, $order->due_amount->amount());
        $this->assertEqualsWithDelta(26.4, $order->payments()->sum('amount'), 0.001);
        $this->assertNotNull($order->getInvoice());
        $this->assertEqualsWithDelta(26.4, $order->getInvoice()->total->amount(), 0.001);
        $this->assertEqualsWithDelta(26.4, OrderResourceFactory::order($order)['total'], 0.001);
    }
}
