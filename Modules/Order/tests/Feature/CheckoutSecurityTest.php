<?php

namespace Modules\Order\Tests\Feature;

use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;
use Modules\Branch\Models\Branch;
use Modules\Menu\Models\Menu;
use Modules\Option\Enums\OptionType;
use Modules\Option\Models\Option;
use Modules\Option\Models\OptionValue;
use Modules\Order\Http\Controllers\Api\V1\OrderController;
use Modules\Order\Models\Order;
use Modules\Pos\Models\PosRegister;
use Modules\Pos\Models\PosSession;
use Modules\Product\Models\Product;
use Modules\Support\Enums\PriceType;
use Modules\Tax\Enums\TaxType;
use Modules\Tax\Models\Tax;
use Modules\User\Enums\DefaultRole;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\NotificationWorkflowTestCase;

class CheckoutSecurityTest extends NotificationWorkflowTestCase
{
    private function checkout(): array
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
        Route::get('/test-checkout/orders/{orderId}/edit', [OrderController::class, 'edit']);
        Route::post('/test-checkout/orders/{orderId}/quote', [OrderController::class, 'quote']);
        Route::put('/test-checkout/orders/{orderId}', [OrderController::class, 'update']);
        Route::post('/test-checkout/orders/{orderId}/payment', [OrderController::class, 'storePayment']);
        $payload = ['branch_id' => $branch->id, 'menu_id' => $menu->id, 'register_id' => $register->id, 'session_id' => $session->id, 'submit_action' => 'send_to_kitchen', 'type' => 'takeaway', 'items' => [
            ['line_id' => 'soup', 'product_id' => $product->id, 'qty' => 2, 'options' => [$option->id => $value->id]],
        ]];

        return compact('payload', 'product', 'option', 'value', 'branch', 'session', 'user');
    }

    public function test_tampered_or_expired_quotes_never_create_an_order(): void
    {
        ['payload' => $data] = $this->checkout();
        $quote = $this->postJson('/test-checkout/quote', $data)->assertOk()->json('body.quote_token');
        $data['idempotency_key'] = (string) Str::uuid();
        $data['quote_token'] = $quote.'tampered';
        $this->postJson('/test-checkout/orders', $data)->assertConflict()->assertJsonPath('errors.reason', 'quote_changed')->assertJsonPath('errors.detail', 'quote_invalid');
        $claims = json_decode(Crypt::decryptString($quote), true);
        $claims['scope'] = 'another-checkout';
        $data['quote_token'] = Crypt::encryptString(json_encode($claims));
        $this->postJson('/test-checkout/orders', $data)->assertConflict()->assertJsonPath('errors.detail', 'quote_context_changed');
        $data['quote_token'] = $quote;
        $this->travel(61)->minutes();
        $this->postJson('/test-checkout/orders', $data)->assertConflict()->assertJsonPath('errors.detail', 'quote_expired');
        $this->assertDatabaseCount('orders', 0);
        $this->assertDatabaseCount('order_submissions', 0);
    }

    public function test_changed_price_requires_a_new_confirmation(): void
    {
        ['payload' => $data,'product' => $product] = $this->checkout();
        $quote = $this->postJson('/test-checkout/quote', $data)->assertOk()->json('body');
        $data['idempotency_key'] = (string) Str::uuid();
        $data['quote_token'] = $quote['quote_token'];
        $product->update(['price' => 15]);
        $changed = $this->postJson('/test-checkout/orders', $data)->assertConflict()->assertJsonPath('errors.reason', 'quote_changed')->assertJsonPath('errors.detail', 'price_changed');
        $this->assertDatabaseCount('orders', 0);
        $this->assertEqualsWithDelta(37.4, $changed->json('body.pricing.total.amount'), 0.001);
        $data['quote_token'] = $changed->json('body.quote_token');
        $this->postJson('/test-checkout/orders', $data)->assertOk();
        $this->assertEqualsWithDelta(37.4, Order::firstOrFail()->total->amount(), 0.001);
    }

    public function test_displayed_price_can_commit_in_one_request_and_replay_safely(): void
    {
        ['payload' => $data] = $this->checkout();
        $pricing = $this->postJson('/test-checkout/quote', $data)->assertOk()->json('body.pricing');
        $pricing = array_intersect_key($pricing, array_flip(['items', 'quantity', 'subTotal', 'total', 'taxes', 'charges']));
        $pricing['items'] = array_map(
            fn (array $line) => array_intersect_key($line, array_flip(['line_id', 'qty', 'unitPrice', 'subtotal', 'taxTotal', 'total', 'taxes'])),
            $pricing['items'],
        );
        $pricing['discount'] = null;
        $data['displayed_pricing'] = json_encode($pricing, JSON_THROW_ON_ERROR);
        $data['idempotency_key'] = (string) Str::uuid();

        $first = $this->postJson('/test-checkout/orders', $data)->assertOk();
        $this->postJson('/test-checkout/orders', $data)
            ->assertOk()
            ->assertJsonPath('body.id', $first->json('body.id'));
        $this->assertDatabaseCount('orders', 1);
    }

    public function test_changed_displayed_price_requires_confirmation_before_commit(): void
    {
        ['payload' => $data, 'product' => $product] = $this->checkout();
        $pricing = $this->postJson('/test-checkout/quote', $data)->assertOk()->json('body.pricing');
        $pricing['discount']['value'] = null;
        $data['displayed_pricing'] = json_encode($pricing, JSON_THROW_ON_ERROR);
        $data['idempotency_key'] = (string) Str::uuid();
        $product->update(['price' => 15]);

        $changed = $this->postJson('/test-checkout/orders', $data)
            ->assertConflict()
            ->assertJsonPath('errors.detail', 'price_changed');
        $this->assertDatabaseCount('orders', 0);

        $data['quote_token'] = $changed->json('body.quote_token');
        $this->postJson('/test-checkout/orders', $data)->assertOk();
        $this->assertDatabaseCount('orders', 1);
    }

    public function test_displayed_breakdown_change_is_rejected_even_when_total_matches(): void
    {
        ['payload' => $data] = $this->checkout();
        $pricing = $this->postJson('/test-checkout/quote', $data)->assertOk()->json('body.pricing');
        $pricing['discount']['value'] = null;
        $pricing['taxes'][0]['rate'] = 9;
        $data['displayed_pricing'] = json_encode($pricing, JSON_THROW_ON_ERROR);
        $data['idempotency_key'] = (string) Str::uuid();

        $this->postJson('/test-checkout/orders', $data)
            ->assertConflict()
            ->assertJsonPath('errors.detail', 'price_changed');
        $this->assertDatabaseCount('orders', 0);
    }

    public function test_malformed_displayed_pricing_is_rejected_without_saving(): void
    {
        ['payload' => $data] = $this->checkout();
        $data['displayed_pricing'] = '{}';
        $data['idempotency_key'] = (string) Str::uuid();

        $this->postJson('/test-checkout/orders', $data)
            ->assertUnprocessable()
            ->assertJsonValidationErrors('displayed_pricing');
        $this->assertDatabaseCount('orders', 0);
    }

    public function test_display_changes_do_not_invalidate_unchanged_prices(): void
    {
        ['payload' => $data, 'product' => $product, 'value' => $value] = $this->checkout();
        $data['quote_token'] = $this->postJson('/test-checkout/quote', $data)->assertOk()->json('body.quote_token');
        $data['idempotency_key'] = (string) Str::uuid();
        $product->update(['name' => ['en' => 'Renamed soup']]);
        $value->update(['label' => ['en' => 'Renamed size']]);
        $this->postJson('/test-checkout/orders', $data)->assertOk();
        $this->assertEqualsWithDelta(26.4, Order::firstOrFail()->total->amount(), 0.001);
    }

    public function test_quote_preserves_the_draft_product_order(): void
    {
        ['payload' => $data, 'product' => $product] = $this->checkout();
        $secondProduct = Product::factory()->create([
            'menu_id' => $product->menu_id,
            'name' => ['en' => 'Salad'],
            'price' => 8,
            'special_price' => null,
            'is_active' => true,
        ]);
        $data['items'] = [
            ['line_id' => 'salad', 'product_id' => $secondProduct->id, 'qty' => 1, 'options' => []],
            ...$data['items'],
        ];

        $response = $this->postJson('/test-checkout/quote', $data)->assertOk();

        $this->assertSame(
            ['salad', 'soup'],
            array_column($response->json('body.pricing.items'), 'line_id'),
        );
    }

    public function test_checkout_batches_catalog_reads_for_new_and_edited_orders(): void
    {
        ['payload' => $data, 'product' => $product, 'option' => $option] = $this->checkout();
        $data['submit_action'] = 'hold_order';
        $lines = $data['items'];
        for ($index = 1; $index < 10; $index++) {
            $copy = $product->replicate();
            $copy->sku = 'CHECKOUT-PERF-'.$index;
            $copy->save();
            $copy->options()->sync([$option->id]);
            $lines[] = [...$lines[0], 'line_id' => 'line-'.$index, 'product_id' => $copy->id];
        }

        foreach ([1, 10] as $count) {
            $data['items'] = array_slice($lines, 0, $count);
            unset($data['edit_version']);
            $quote = $this->measureCheckout('quote', $count, fn () => $this->postJson('/test-checkout/quote', $data));
            $save = [...$data, 'quote_token' => $quote->json('body.quote_token'), 'idempotency_key' => (string) Str::uuid()];
            $id = $this->measureCheckout('submit', $count, fn () => $this->postJson('/test-checkout/orders', $save))->json('body.id');
            $edit = $this->getJson('/test-checkout/orders/'.$id.'/edit')->assertOk()->json('body');
            $savedLines = collect($edit['cart']['items'])->keyBy('item.id');
            foreach ($data['items'] as &$line) {
                $line['order_product_id'] = $savedLines[$line['product_id']]['orderProduct']['id'];
            }
            unset($line);
            $data['edit_version'] = $edit['edit_version'];
            $quote = $this->measureCheckout('edit_quote', $count, fn () => $this->postJson('/test-checkout/orders/'.$id.'/quote', $data));
            $save = [...$data, 'quote_token' => $quote->json('body.quote_token'), 'idempotency_key' => (string) Str::uuid()];
            $this->measureCheckout('edit_submit', $count, fn () => $this->putJson('/test-checkout/orders/'.$id, $save));
            $order = Order::findOrFail($id);
            $this->assertEqualsWithDelta(26.4 * $count, $order->total->amount(), 0.001);
            $this->assertCount($count, $order->products);
            foreach ($order->products as $saved) {
                $this->assertEquals($data['items'][0]['options'][$option->id], $saved->options->sole()->values->sole()->id);
            }
        }
    }

    /**
     * Measure complete checkout requests without logging selections, tokens or customer data.
     */
    private function measureCheckout(string $operation, int $lines, callable $request): TestResponse
    {
        DB::enableQueryLog();
        DB::flushQueryLog();
        $started = hrtime(true);
        try {
            $response = $request()->assertOk();
            $queries = collect(DB::getQueryLog());
        } finally {
            DB::disableQueryLog();
            DB::flushQueryLog();
        }
        $catalogReads = $queries->filter(fn ($query) => preg_match('/^select .* from ["`]products["`]/', $query['query']));
        if (getenv('CHECKOUT_PROFILE')) {
            fwrite(STDERR, json_encode([
                'operation' => $operation, 'lines' => $lines,
                'duration_ms' => round((hrtime(true) - $started) / 1e6, 2),
                'queries' => $queries->count(), 'catalog_reads' => $catalogReads->count(),
                'selects' => $queries->filter(fn ($query) => str_starts_with($query['query'], 'select'))
                    ->countBy(fn ($query) => preg_match('/ from ["`]([^"`]+)["`]/', $query['query'], $matches) ? $matches[1] : 'other')->all(),
            ], JSON_THROW_ON_ERROR).PHP_EOL);
        }
        $budget = match ($operation) {
            'quote' => 1,
            'submit', 'edit_quote' => 3,
            'edit_submit' => 7,
        };
        $this->assertLessThanOrEqual($budget, $catalogReads->count(), 'Catalog reads must stay bounded as the line count grows during '.$operation);

        return $response;
    }

    public function test_changed_selections_cannot_reuse_a_quote(): void
    {
        ['payload' => $data] = $this->checkout();
        $data['quote_token'] = $this->postJson('/test-checkout/quote', $data)->assertOk()->json('body.quote_token');
        $data['idempotency_key'] = (string) Str::uuid();
        $data['items'][0]['qty'] = 3;
        $this->postJson('/test-checkout/orders', $data)->assertConflict()->assertJsonPath('errors.detail', 'quote_input_changed');
        $this->assertDatabaseCount('orders', 0);
    }

    public function test_unchanged_browser_payload_can_be_confirmed_before_expiry(): void
    {
        ['payload' => $data] = $this->checkout();
        $data = [...$data, 'customer_id' => null, 'table_id' => null, 'waiter_id' => null,
            'notes' => null, 'refund_payment_method' => null, 'gift_card_code' => null,
            'car_plate' => null, 'car_description' => null, 'scheduled_at' => null, 'guest_count' => 1];
        $data['quote_token'] = $this->postJson('/test-checkout/quote', $data)->assertOk()->json('body.quote_token');
        $data['idempotency_key'] = (string) Str::uuid();
        $this->travel(59)->minutes();
        $this->postJson('/test-checkout/orders', $data)->assertOk();
        $this->assertDatabaseCount('orders', 1);
    }

    public function test_rejects_forged_source_gifts_prices_and_option_membership(): void
    {
        ['payload' => $data,'option' => $option] = $this->checkout();
        foreach (['total' => 0.01, 'taxes' => [['amount' => 0]], 'source' => 'table_qr', 'gift_id' => 98765] as $key => $value) {
            $this->postJson('/test-checkout/quote', [...$data, $key => $value])->assertUnprocessable();
        }
        $bad = $data;
        $bad['items'][0]['price'] = 0.01;
        $this->postJson('/test-checkout/quote', $bad)->assertUnprocessable();
        $bad = $data;
        $bad['items'][0]['options'] = [$option->id => 98765];
        $this->postJson('/test-checkout/quote', $bad)->assertUnprocessable()->assertJsonValidationErrors('items.0.options.'.$option->id);
        $bad = $data;
        $bad['items'][0]['options'] = [$option->id => [$data['items'][0]['options'][$option->id]]];
        $this->postJson('/test-checkout/quote', $bad)->assertUnprocessable();
        $bad = $data;
        $bad['items'][0]['order_product_id'] = 123;
        $this->postJson('/test-checkout/quote', $bad)->assertUnprocessable();
        $this->assertDatabaseCount('orders', 0);
    }

    public function test_edit_checks_revision_and_preserves_order_line_identity(): void
    {
        ['payload' => $data] = $this->checkout();
        $data['submit_action'] = 'hold_order';
        $create = [...$data, 'quote_token' => $this->postJson('/test-checkout/quote', $data)->assertOk()->json('body.quote_token'), 'idempotency_key' => (string) Str::uuid()];
        $id = $this->postJson('/test-checkout/orders', $create)->assertOk()->json('body.id');
        $edit = $this->getJson('/test-checkout/orders/'.$id.'/edit')->assertOk()->json('body');
        $line = $edit['cart']['items'][0];
        $data['items'][0]['order_product_id'] = $line['orderProduct']['id'];
        $data['items'][0]['qty'] = 3;
        $data['edit_version'] = $edit['edit_version'];
        $quote = $this->postJson('/test-checkout/orders/'.$id.'/quote', $data)->assertOk();
        $save = [...$data, 'quote_token' => $quote->json('body.quote_token'), 'idempotency_key' => (string) Str::uuid()];
        Order::findOrFail($id)->update(['notes' => 'another cashier']);
        $this->putJson('/test-checkout/orders/'.$id, $save)->assertConflict();
        $this->assertEquals(2, Order::findOrFail($id)->products()->sum('quantity'));
        $data['edit_version'] = $this->getJson('/test-checkout/orders/'.$id.'/edit')->assertOk()->json('body.edit_version');
        $quote = $this->postJson('/test-checkout/orders/'.$id.'/quote', $data)->assertOk();
        $save = [...$data, 'quote_token' => $quote->json('body.quote_token'), 'idempotency_key' => (string) Str::uuid()];
        $this->putJson('/test-checkout/orders/'.$id, $save)->assertOk();
        $this->putJson('/test-checkout/orders/'.$id, $save)->assertOk();
        $order = Order::findOrFail($id);
        $this->assertEquals(3, $order->products()->sum('quantity'));
        $this->assertSame($line['orderProduct']['id'], $order->products()->first()->id);
        $this->assertDatabaseCount('orders', 1);
    }

    public static function editableOptionTypes(): array
    {
        return [[OptionType::Select], [OptionType::Radio], [OptionType::MultipleSelect], [OptionType::Checkbox], [OptionType::Text]];
    }

    #[DataProvider('editableOptionTypes')]
    public function test_edit_persists_changed_options_and_removes_cleared_options(OptionType $type): void
    {
        ['payload' => $data, 'option' => $option, 'value' => $oldValue, 'branch' => $branch] = $this->checkout();
        $option->update(['is_required' => false, 'type' => $type]);
        $multiple = in_array($type, [OptionType::MultipleSelect, OptionType::Checkbox], true);
        if ($multiple) {
            $data['items'][0]['options'][$option->id] = [$oldValue->id];
        } elseif ($type->isFieldType()) {
            $data['items'][0]['options'][$option->id] = 'Original note';
        }
        $newValue = OptionValue::factory()->create(['option_id' => $option->id, 'branch_id' => $branch->id, 'label' => ['en' => 'Small'], 'price' => 3, 'price_type' => PriceType::Fixed]);
        $create = [...$data, 'quote_token' => $this->postJson('/test-checkout/quote', $data)->assertOk()->json('body.quote_token'), 'idempotency_key' => (string) Str::uuid()];
        $id = $this->postJson('/test-checkout/orders', $create)->assertOk()->json('body.id');
        $lineId = Order::findOrFail($id)->products()->firstOrFail()->id;
        $updatedValue = $type->isFieldType() ? 'No sugar' : ($multiple ? [$newValue->id] : $newValue->id);
        foreach ([[$option->id => $updatedValue], []] as $selection) {
            $edit = $this->getJson('/test-checkout/orders/'.$id.'/edit')->assertOk()->json('body');
            $data['edit_version'] = $edit['edit_version'];
            $data['items'][0]['order_product_id'] = $lineId;
            $data['items'][0]['options'] = $selection;
            $quote = $this->postJson('/test-checkout/orders/'.$id.'/quote', $data)->assertOk()->json('body');
            $save = [...$data, 'quote_token' => $quote['quote_token'], 'idempotency_key' => (string) Str::uuid()];
            $this->putJson('/test-checkout/orders/'.$id, $save)->assertOk();
            $this->putJson('/test-checkout/orders/'.$id, $save)->assertOk();
            $order = Order::findOrFail($id);
            $line = $order->products()->firstOrFail();
            $this->assertSame($lineId, $line->id);
            $this->assertEqualsWithDelta($selection ? ($type->isFieldType() ? 26.4 : 28.6) : 22, $order->total->amount(), 0.001);
            $this->assertSame($selection ? [$type->isFieldType() ? $oldValue->id : $newValue->id] : [], $line->options->flatMap(fn ($item) => $item->values->pluck('id'))->all());
            if (! $type->isFieldType() || ! $selection) {
                $this->assertDatabaseMissing('order_product_option_values', ['option_value_id' => $oldValue->id]);
            }
            $reopened = $this->getJson('/test-checkout/orders/'.$id.'/edit')->assertOk()->json('body.cart.items.0');
            $this->assertEquals($selection, $reopened['option_input']);
            $this->assertEqualsWithDelta($quote['pricing']['items'][0]['total']['amount'], $reopened['total']['amount'], 0.001);
        }
    }

    public function test_sending_an_edited_held_order_to_kitchen_confirms_it_once(): void
    {
        ['payload' => $data] = $this->checkout();
        $data['submit_action'] = 'hold_order';
        $create = [...$data, 'quote_token' => $this->postJson('/test-checkout/quote', $data)->assertOk()->json('body.quote_token'), 'idempotency_key' => (string) Str::uuid()];
        $id = $this->postJson('/test-checkout/orders', $create)->assertOk()->json('body.id');
        $edit = $this->getJson('/test-checkout/orders/'.$id.'/edit')->assertOk()->json('body');
        $data['items'][0]['order_product_id'] = $edit['cart']['items'][0]['orderProduct']['id'];
        $data['items'][0]['qty'] = 3;
        $data['edit_version'] = $edit['edit_version'];
        $data['submit_action'] = 'send_to_kitchen';
        $quote = $this->postJson('/test-checkout/orders/'.$id.'/quote', $data)->assertOk();
        $save = [...$data, 'quote_token' => $quote->json('body.quote_token'), 'idempotency_key' => (string) Str::uuid()];
        $this->putJson('/test-checkout/orders/'.$id, $save)->assertOk();
        $order = Order::findOrFail($id);
        $this->assertSame(\Modules\Order\Enums\OrderStatus::Confirmed, $order->status);
        $this->assertEquals(3, $order->products()->sum('quantity'));
        $logs = $order->statusLogs()->count();
        $this->putJson('/test-checkout/orders/'.$id, $save)->assertOk();
        $this->assertSame($logs, $order->statusLogs()->count());
        $this->assertDatabaseCount('orders', 1);
    }

    /**
     * Prepare a partially paid order whose reduced quantity creates a server-calculated refund.
     */
    private function overpaidGiftCardEdit(): array
    {
        ['payload' => $data, 'branch' => $branch] = $this->checkout();
        $create = [...$data, 'quote_token' => $this->postJson('/test-checkout/quote', $data)->assertOk()->json('body.quote_token'), 'idempotency_key' => (string) Str::uuid()];
        $id = $this->postJson('/test-checkout/orders', $create)->assertOk()->json('body.id');
        $order = Order::findOrFail($id);
        $this->postJson('/test-checkout/orders/'.$order->reference_no.'/payment', [
            'session_id' => $data['session_id'], 'register_id' => $data['register_id'], 'payment_mode' => 'partial',
            'payments' => [['method' => 'card', 'amount' => 20]], 'with_print' => false,
        ])->assertOk();
        $edit = $this->getJson('/test-checkout/orders/'.$id.'/edit')->assertOk()->json('body');
        $data['items'][0]['order_product_id'] = $edit['cart']['items'][0]['orderProduct']['id'];
        $data['items'][0]['qty'] = 1;
        $data['edit_version'] = $edit['edit_version'];
        $data['refund_payment_method'] = 'gift_card';
        $card = \Modules\GiftCard\Models\GiftCard::factory()->create([
            'branch_id' => $branch->id, 'liability_branch_id' => $branch->id, 'currency' => 'USD',
            'customer_id' => null, 'expiry_date' => null, 'initial_balance' => 30, 'current_balance' => 30,
        ]);
        $data['gift_card_code'] = $card->code;

        return compact('data', 'id', 'card');
    }

    public function test_overpayment_credits_the_selected_gift_card_once_and_replays_after_expiry(): void
    {
        ['data' => $data, 'id' => $id, 'card' => $card] = $this->overpaidGiftCardEdit();
        $quote = $this->postJson('/test-checkout/orders/'.$id.'/quote', $data)->assertOk();
        $save = [...$data, 'quote_token' => $quote->json('body.quote_token'), 'idempotency_key' => (string) Str::uuid()];
        $this->putJson('/test-checkout/orders/'.$id, $save)->assertOk();
        $this->assertEqualsWithDelta(36.8, $card->fresh()->getRawOriginal('current_balance'), 0.0001);
        $this->assertDatabaseHas('payments', ['order_id' => $id, 'method' => 'gift_card', 'type' => 'refund', 'amount' => 6.8]);
        $this->assertDatabaseCount('gift_card_transactions', 1);
        $card->update(['expiry_date' => now()->subDay()]);
        $this->travel(61)->minutes();
        $this->putJson('/test-checkout/orders/'.$id, $save)->assertOk();
        $this->assertDatabaseCount('gift_card_transactions', 1);
        $this->assertEqualsWithDelta(36.8, $card->fresh()->getRawOriginal('current_balance'), 0.0001);
        $save['gift_card_code'] = 'DIFFERENT-CARD';
        $this->putJson('/test-checkout/orders/'.$id, $save)->assertConflict();
    }

    public function test_invalid_refund_destinations_do_not_modify_the_order_or_card(): void
    {
        ['data' => $data, 'id' => $id, 'card' => $card] = $this->overpaidGiftCardEdit();
        foreach ([null, '', 'MISSING-CARD'] as $code) {
            $this->postJson('/test-checkout/orders/'.$id.'/quote', [...$data, 'gift_card_code' => $code])
                ->assertUnprocessable()->assertJsonValidationErrors('gift_card_code');
        }
        foreach ([['status' => 'inactive'], ['status' => 'disabled'], ['status' => 'active', 'expiry_date' => now()->subDay()],
            ['expiry_date' => null, 'customer_id' => auth()->id()]] as $changes) {
            $card->update($changes);
            $this->postJson('/test-checkout/orders/'.$id.'/quote', $data)
                ->assertUnprocessable()->assertJsonValidationErrors('gift_card_code');
        }
        $otherBranch = Branch::factory()->create();
        $otherCard = \Modules\GiftCard\Models\GiftCard::factory()->create([
            'branch_id' => $otherBranch->id, 'customer_id' => null, 'expiry_date' => null,
        ]);
        $this->postJson('/test-checkout/orders/'.$id.'/quote', [...$data, 'gift_card_code' => $otherCard->code])
            ->assertUnprocessable()->assertJsonValidationErrors('gift_card_code');
        $this->assertDatabaseMissing('payments', ['order_id' => $id, 'type' => 'refund']);
        $this->assertEquals(2, Order::findOrFail($id)->products()->sum('quantity'));
        $this->assertEquals(30, $card->fresh()->getRawOriginal('current_balance'));
    }

    public function test_disabling_a_card_after_quote_prevents_the_refund_atomically(): void
    {
        ['data' => $data, 'id' => $id, 'card' => $card] = $this->overpaidGiftCardEdit();
        $quote = $this->postJson('/test-checkout/orders/'.$id.'/quote', $data)->assertOk();
        $card->update(['status' => 'disabled']);
        $this->putJson('/test-checkout/orders/'.$id, [...$data, 'quote_token' => $quote->json('body.quote_token'), 'idempotency_key' => (string) Str::uuid()])
            ->assertUnprocessable()->assertJsonValidationErrors('gift_card_code');
        $this->assertDatabaseMissing('payments', ['order_id' => $id, 'type' => 'refund']);
        $this->assertEquals(2, Order::findOrFail($id)->products()->sum('quantity'));
        $this->assertEquals(30, $card->fresh()->getRawOriginal('current_balance'));
    }
}
