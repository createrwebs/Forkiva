<?php

namespace Modules\SeatingPlan\tests\Feature;

use Illuminate\Contracts\Console\Kernel;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Modules\Option\Enums\OptionType;
use Modules\Order\Enums\OrderPaymentStatus;
use Modules\Order\Enums\OrderProductStatus;
use Modules\Order\Enums\OrderSource;
use Modules\Order\Enums\OrderStatus;
use Modules\Order\Enums\OrderType;
use Modules\Order\Models\Order;
use Modules\Product\Models\Product;
use Modules\SeatingPlan\Enums\TableShape;
use Modules\SeatingPlan\Enums\TableStatus;
use Modules\SeatingPlan\Models\Table;
use Modules\SeatingPlan\Services\TableQrOrder\TableQrOrderServiceInterface;
use Modules\Support\Enums\PriceType;
use Modules\Tax\Enums\TaxType;
use Tests\TestCase;

class TableQrOrderingTest extends TestCase
{
    use RefreshDatabase;

    public function createApplication()
    {
        putenv('APP_INSTALLED=false');
        $_ENV['APP_INSTALLED'] = 'false';
        $_SERVER['APP_INSTALLED'] = 'false';

        $app = require __DIR__.'/../../../../bootstrap/app.php';
        $app->make(Kernel::class)->bootstrap();

        return $app;
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->fakeTableQrSettings();
    }

    public function test_context_returns_products_from_selected_table_branch_active_menu_only(): void
    {
        $this->withoutMiddleware();

        [$table, $product] = $this->tableQrFixture();
        $this->createInactiveMenuProduct($table->branch_id, 'INACTIVE-MENU-PRODUCT');
        $this->createOtherBranchProduct('OTHER-BRANCH-PRODUCT');

        $response = $this
            ->getJson($this->tableQrUrl($table, (string) Str::uuid(), 'context'))
            ->assertOk();

        $productIds = collect($response->json('body.products'))->pluck('id')->all();

        $this->assertSame([$product->id], $productIds);
    }

    public function test_service_context_returns_products_from_selected_table_branch_active_menu_only(): void
    {
        [$table, $product] = $this->tableQrFixture();
        $this->createInactiveMenuProduct($table->branch_id, 'INACTIVE-MENU-PRODUCT');
        $this->createOtherBranchProduct('OTHER-BRANCH-PRODUCT');

        $payload = app(TableQrOrderServiceInterface::class)->context(
            uuid: $table->uuid,
            token: $table->table_qr_token,
            request: Request::create('/table-qr/context')
        );

        $productIds = collect($payload['products'])->pluck('id')->all();

        $this->assertSame([$product->id], $productIds);
    }

    public function test_context_eager_loads_product_taxes_with_lazy_loading_disabled(): void
    {
        $this->withoutMiddleware();
        [$table, $product] = $this->tableQrFixture();
        $otherProductId = $this->createProductForMenu($product->menu_id, 'QR-SECOND-PRODUCT');
        $taxId = DB::table('taxes')->insertGetId([
            'branch_id' => $table->branch_id,
            'name' => json_encode(['en' => 'Sales tax', 'ar' => 'Sales tax'], JSON_THROW_ON_ERROR),
            'code' => 'QR-SALES',
            'rate' => 16,
            'type' => TaxType::Exclusive->value,
            'compound' => false,
            'order_types' => json_encode([OrderType::DineIn->value], JSON_THROW_ON_ERROR),
            'is_global' => false,
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $product->taxes()->attach($taxId);

        $preventedLazyLoading = Model::preventsLazyLoading();
        Model::preventLazyLoading();
        DB::enableQueryLog();
        DB::flushQueryLog();

        try {
            $response = $this->getJson($this->tableQrUrl($table, '', 'context'))->assertOk();
            $products = collect($response->json('body.products'))->keyBy('id');
            $this->assertCount(2, $products);
            $this->assertSame($taxId, $products[$product->id]['pricing_taxes'][0]['id']);
            $this->assertEquals(16, $products[$product->id]['pricing_taxes'][0]['rate']);
            $this->assertSame(TaxType::Exclusive->value, $products[$product->id]['pricing_taxes'][0]['type']);
            $this->assertSame([OrderType::DineIn->value], $products[$product->id]['pricing_taxes'][0]['order_types']);
            $this->assertSame([], $products[$otherProductId]['pricing_taxes']);
            $taxQueries = collect(DB::getQueryLog())->filter(
                fn (array $query) => str_contains($query['query'], 'product_taxes')
            );
            $this->assertCount(1, $taxQueries);
        } finally {
            Model::preventLazyLoading($preventedLazyLoading);
            DB::disableQueryLog();
        }
    }

    public function test_context_returns_no_active_order_tracking_for_available_table(): void
    {
        $this->withoutMiddleware();

        [$table] = $this->tableQrFixture();

        $this
            ->getJson($this->tableQrUrl($table, (string) Str::uuid(), 'context'))
            ->assertOk()
            ->assertJsonPath('body.active_order', null);
    }

    public function test_context_returns_customer_order_tracking_for_active_table_order(): void
    {
        $this->withoutMiddleware();

        [$table, $product] = $this->tableQrFixture();
        $order = $this->createTrackedTableOrder($table, $product);

        $response = $this
            ->getJson($this->tableQrUrl($table->fresh(), (string) Str::uuid(), 'context'))
            ->assertOk()
            ->assertJsonPath('body.active_order.id', $order->id)
            ->assertJsonPath('body.active_order.status.id', OrderStatus::Preparing->value)
            ->assertJsonPath('body.active_order.source.id', OrderSource::TableQr->value)
            ->assertJsonPath('body.active_order.items.0.status.id', OrderProductStatus::Preparing->value)
            ->assertJsonPath('body.active_order.items.0.options.0.name', 'Size')
            ->assertJsonPath('body.active_order.items.0.options.0.values.0.label', 'Large')
            ->assertJsonPath('body.active_order.progress.preparing', 2)
            ->assertJsonPath('body.active_order.progress_steps.1.status.id', OrderProductStatus::Preparing->value)
            ->assertJsonPath('body.active_order.progress_steps.1.status.color', OrderProductStatus::Preparing->color())
            ->assertJsonPath('body.active_order.progress_steps.1.value', 2);

        $this->assertSame($order->reference_no, $response->json('body.active_order.reference_no'));
        $this->assertSame(2, $response->json('body.active_order.items_count'));
        $this->assertSame('Burger', $response->json('body.active_order.items.0.name'));
    }

    public function test_tracking_returns_only_lightweight_customer_order_state(): void
    {
        $this->withoutMiddleware();

        [$table, $product] = $this->tableQrFixture();
        $order = $this->createTrackedTableOrder($table, $product);
        DB::enableQueryLog();
        DB::flushQueryLog();

        $response = $this
            ->getJson($this->tableQrUrl($table->fresh(), (string) Str::uuid(), 'tracking'))
            ->assertOk()
            ->assertJsonPath('body.active_order.id', $order->id)
            ->assertJsonPath('body.active_order.items.0.name', 'Burger')
            ->assertJsonPath('body.table.status.id', TableStatus::Occupied->value)
            ->assertJsonPath('body.session.available', false);
        $queries = collect(DB::getQueryLog())->pluck('query');
        DB::disableQueryLog();

        $this->assertSame(
            ['active_order', 'session', 'table'],
            collect($response->json('body'))->keys()->sort()->values()->all()
        );
        $this->assertNotEmpty($queries);
        $this->assertFalse($queries->contains(
            fn (string $query) => str_contains(strtolower($query), 'categories')
        ));
        $response
            ->assertJsonMissingPath('body.cart')
            ->assertJsonMissingPath('body.categories')
            ->assertJsonMissingPath('body.products')
            ->assertJsonMissingPath('body.settings');
    }

    public function test_quote_requires_an_open_session_without_persisting_a_cart(): void
    {
        $this->withoutMiddleware();
        [$table,$product] = $this->tableQrFixture();
        $payload = ['items' => [['line_id' => 'burger', 'product_id' => $product->id, 'qty' => 1, 'options' => []]], 'guest_count' => 2];
        $this->postJson($this->tableQrUrl($table, '', 'quote'), $payload)->assertStatus(409)->assertJsonPath('errors.reason', 'no_open_pos_session');
        $this->assertDatabaseCount('order_submissions', 0);
    }

    public function test_stateless_submit_replays_and_appends_without_a_server_cart(): void
    {
        $this->withoutMiddleware();
        \Illuminate\Support\Facades\Notification::fake();
        [$table,$product] = $this->tableQrFixture();
        $user = \Modules\User\Models\User::factory()->create(['branch_id' => $table->branch_id]);
        $register = \Modules\Pos\Models\PosRegister::factory()->create(['branch_id' => $table->branch_id, 'is_active' => true]);
        \Modules\Pos\Models\PosSession::factory()->create(['branch_id' => $table->branch_id, 'pos_register_id' => $register->id, 'opened_by' => $user->id, 'opened_at' => now()]);
        $payload = ['items' => [['line_id' => 'burger', 'product_id' => $product->id, 'qty' => 1, 'options' => []]], 'guest_count' => 2];
        $quote = $this->postJson($this->tableQrUrl($table, '', 'quote'), $payload)->assertOk();
        $this->assertSame('burger', $quote->json('body.pricing.items.0.line_id'));
        $this->assertEquals(5, $quote->json('body.pricing.total.amount'));
        $payload['quote_token'] = $quote->json('body.quote_token');
        $payload['idempotency_key'] = (string) Str::uuid();
        $saved = $this->postJson($this->tableQrUrl($table, '', 'submit'), $payload)->assertOk();
        $replay = $this->postJson($this->tableQrUrl($table, '', 'submit'), $payload)->assertOk();
        $this->assertSame($saved->json(), $replay->json());
        $this->assertDatabaseCount('orders', 1);
        $payload['items'][0]['line_id'] = 'second-burger';
        unset($payload['quote_token'],$payload['idempotency_key']);
        $quote = $this->postJson($this->tableQrUrl($table, '', 'quote'), $payload)->assertOk();
        $newItems = collect($quote->json('body.pricing.items'))->filter(fn ($item) => empty($item['orderProduct']))->values();
        $this->assertCount(1, $newItems);
        $this->assertSame('second-burger', $newItems[0]['line_id']);
        $this->assertEquals(10, $quote->json('body.pricing.total.amount'));
        $this->assertSame('second-burger', $quote->json('body.comparison_pricing.items.0.line_id'));
        $this->assertEquals(5, $quote->json('body.comparison_pricing.total.amount'));
        $payload['quote_token'] = $quote->json('body.quote_token');
        $payload['idempotency_key'] = (string) Str::uuid();
        $this->postJson($this->tableQrUrl($table, '', 'submit'), $payload)->assertOk();
        $this->assertDatabaseCount('orders', 1);
        $this->assertEquals(2, Order::firstOrFail()->products()->sum('quantity'));
        $this->assertEquals(10, Order::firstOrFail()->total->amount());
    }

    public function test_repeated_additions_preserve_options_quantities_and_tax_totals(): void
    {
        $this->withoutMiddleware();
        \Illuminate\Support\Facades\Notification::fake();
        [$table, $product] = $this->tableQrFixture();
        $user = \Modules\User\Models\User::factory()->create(['branch_id' => $table->branch_id]);
        $register = \Modules\Pos\Models\PosRegister::factory()->create(['branch_id' => $table->branch_id, 'is_active' => true]);
        \Modules\Pos\Models\PosSession::factory()->create(['branch_id' => $table->branch_id, 'pos_register_id' => $register->id, 'opened_by' => $user->id, 'opened_at' => now()]);
        $option = \Modules\Option\Models\Option::create([
            'branch_id' => $table->branch_id,
            'name' => ['en' => 'Size'],
            'type' => OptionType::Select,
            'is_required' => true,
            'is_global' => false,
            'order' => 1,
        ]);
        $small = $option->values()->create(['branch_id' => $table->branch_id, 'label' => ['en' => 'Small'], 'price' => 0.125, 'price_type' => PriceType::Fixed, 'order' => 1]);
        $large = $option->values()->create(['branch_id' => $table->branch_id, 'label' => ['en' => 'Large'], 'price' => 0.25, 'price_type' => PriceType::Fixed, 'order' => 2]);
        $product->options()->attach($option);
        \Modules\Tax\Models\Tax::create([
            'branch_id' => $table->branch_id,
            'name' => ['en' => 'VAT'],
            'code' => 'APPEND-VAT',
            'rate' => 10,
            'type' => TaxType::Exclusive,
            'compound' => false,
            'order_types' => [OrderType::DineIn->value],
            'is_global' => true,
            'is_active' => true,
        ]);

        foreach ([[$small->id, 1, 5.6375], [$large->id, 2, 17.1875], [$small->id, 1, 22.825]] as $index => [$valueId, $qty, $total]) {
            $lineId = 'selection-'.$index;
            $payload = ['items' => [['line_id' => $lineId, 'product_id' => $product->id, 'qty' => $qty, 'options' => [$option->id => $valueId]]]];
            $quote = $this->postJson($this->tableQrUrl($table, '', 'quote'), $payload)->assertOk();
            $line = collect($quote->json('body.pricing.items'))->firstWhere('line_id', $lineId);
            $this->assertNotNull($line);
            $this->assertSame($qty, $line['qty']);
            $this->assertEqualsWithDelta($total, $quote->json('body.pricing.total.amount'), 0.000001);
            $this->assertEquals([$option->id => $valueId], $line['option_input']);
            $payload['quote_token'] = $quote->json('body.quote_token');
            $payload['idempotency_key'] = (string) Str::uuid();
            $this->postJson($this->tableQrUrl($table, '', 'submit'), $payload)->assertOk();
            $this->postJson($this->tableQrUrl($table, '', 'submit'), $payload)->assertOk();
            $this->assertDatabaseCount('orders', 1);
            $this->assertEqualsWithDelta($total, Order::firstOrFail()->total->amount(), 0.000001);
        }
        $this->assertEquals(4, Order::firstOrFail()->products()->sum('quantity'));
        $this->assertEqualsCanonicalizing(
            [$small->id, $large->id, $small->id],
            DB::table('order_product_option_values')->pluck('option_value_id')->all()
        );
    }

    public function test_public_submit_rejects_prices_and_order_product_identity(): void
    {
        $this->withoutMiddleware();
        [$table,$product] = $this->tableQrFixture();
        $payload = ['items' => [['line_id' => 'burger', 'product_id' => $product->id, 'qty' => 1, 'options' => [], 'order_product_id' => 1]], 'total' => 0];
        $this->postJson($this->tableQrUrl($table, '', 'quote'), $payload)->assertUnprocessable();
    }

    /**
     * Build a minimal table QR menu fixture.
     *
     * @return array{0: Table, 1: Product}
     */
    private function tableQrFixture(): array
    {
        $now = now();
        $branchId = DB::table('branches')->insertGetId([
            'name' => json_encode(['en' => 'Main Branch', 'ar' => 'Main Branch'], JSON_THROW_ON_ERROR),
            'legal_name' => 'Forkiva',
            'country_code' => 'JO',
            'timezone' => 'Asia/Amman',
            'currency' => 'JOD',
            'order_types' => json_encode([OrderType::DineIn->value], JSON_THROW_ON_ERROR),
            'payment_methods' => json_encode([], JSON_THROW_ON_ERROR),
            'cash_difference_threshold' => 0,
            'is_active' => true,
            'is_main' => true,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        $menuId = DB::table('menus')->insertGetId([
            'uuid' => (string) Str::uuid(),
            'branch_id' => $branchId,
            'name' => json_encode(['en' => 'Main Menu', 'ar' => 'Main Menu'], JSON_THROW_ON_ERROR),
            'description' => json_encode(['en' => 'Main Menu', 'ar' => 'Main Menu'], JSON_THROW_ON_ERROR),
            'is_active' => true,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        $floorId = DB::table('floors')->insertGetId([
            'branch_id' => $branchId,
            'name' => json_encode(['en' => 'Main Floor', 'ar' => 'Main Floor'], JSON_THROW_ON_ERROR),
            'is_active' => true,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        $zoneId = DB::table('zones')->insertGetId([
            'branch_id' => $branchId,
            'floor_id' => $floorId,
            'name' => json_encode(['en' => 'Indoor', 'ar' => 'Indoor'], JSON_THROW_ON_ERROR),
            'is_active' => true,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        $tableId = DB::table('tables')->insertGetId([
            'uuid' => (string) Str::uuid(),
            'branch_id' => $branchId,
            'floor_id' => $floorId,
            'zone_id' => $zoneId,
            'name' => json_encode(['en' => 'T1', 'ar' => 'T1'], JSON_THROW_ON_ERROR),
            'capacity' => 4,
            'status' => TableStatus::Available->value,
            'shape' => TableShape::Square->value,
            'table_qr_token' => Str::random(64),
            'table_qr_token_regenerated_at' => $now,
            'is_active' => true,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        $categoryId = DB::table('categories')->insertGetId([
            'menu_id' => $menuId,
            'name' => json_encode(['en' => 'Meals', 'ar' => 'Meals'], JSON_THROW_ON_ERROR),
            'is_active' => true,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        $productId = DB::table('products')->insertGetId([
            'menu_id' => $menuId,
            'sku' => 'QR-BURGER',
            'name' => json_encode(['en' => 'Burger', 'ar' => 'Burger'], JSON_THROW_ON_ERROR),
            'description' => json_encode(['en' => 'Burger', 'ar' => 'Burger'], JSON_THROW_ON_ERROR),
            'price' => 5,
            'special_price' => null,
            'special_price_type' => null,
            'special_price_start' => null,
            'special_price_end' => null,
            'new_from' => null,
            'new_to' => null,
            'is_active' => true,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        DB::table('product_categories')->insert([
            'product_id' => $productId,
            'category_id' => $categoryId,
        ]);

        return [
            Table::query()->findOrFail($tableId),
            Product::query()->findOrFail($productId),
        ];
    }

    /**
     * Create a product under an inactive menu for the given branch.
     */
    private function createInactiveMenuProduct(int $branchId, string $sku): int
    {
        $now = now();
        $menuId = DB::table('menus')->insertGetId([
            'uuid' => (string) Str::uuid(),
            'branch_id' => $branchId,
            'name' => json_encode(['en' => $sku, 'ar' => $sku], JSON_THROW_ON_ERROR),
            'description' => json_encode(['en' => $sku, 'ar' => $sku], JSON_THROW_ON_ERROR),
            'is_active' => false,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        return $this->createProductForMenu($menuId, $sku);
    }

    /**
     * Create a product under another branch active menu.
     */
    private function createOtherBranchProduct(string $sku): int
    {
        $now = now();
        $branchId = DB::table('branches')->insertGetId([
            'name' => json_encode(['en' => 'Other Branch', 'ar' => 'Other Branch'], JSON_THROW_ON_ERROR),
            'legal_name' => 'Forkiva',
            'country_code' => 'JO',
            'timezone' => 'Asia/Amman',
            'currency' => 'JOD',
            'order_types' => json_encode([OrderType::DineIn->value], JSON_THROW_ON_ERROR),
            'payment_methods' => json_encode([], JSON_THROW_ON_ERROR),
            'cash_difference_threshold' => 0,
            'is_active' => true,
            'is_main' => false,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        $menuId = DB::table('menus')->insertGetId([
            'uuid' => (string) Str::uuid(),
            'branch_id' => $branchId,
            'name' => json_encode(['en' => 'Other Menu', 'ar' => 'Other Menu'], JSON_THROW_ON_ERROR),
            'description' => json_encode(['en' => 'Other Menu', 'ar' => 'Other Menu'], JSON_THROW_ON_ERROR),
            'is_active' => true,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        return $this->createProductForMenu($menuId, $sku);
    }

    /**
     * Create a menu product with one active category.
     */
    private function createProductForMenu(int $menuId, string $sku): int
    {
        $now = now();
        $categoryId = DB::table('categories')->insertGetId([
            'menu_id' => $menuId,
            'name' => json_encode(['en' => $sku, 'ar' => $sku], JSON_THROW_ON_ERROR),
            'is_active' => true,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        $productId = DB::table('products')->insertGetId([
            'menu_id' => $menuId,
            'sku' => $sku,
            'name' => json_encode(['en' => $sku, 'ar' => $sku], JSON_THROW_ON_ERROR),
            'description' => json_encode(['en' => $sku, 'ar' => $sku], JSON_THROW_ON_ERROR),
            'price' => 5,
            'special_price' => null,
            'special_price_type' => null,
            'special_price_start' => null,
            'special_price_end' => null,
            'new_from' => null,
            'new_to' => null,
            'is_active' => true,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        DB::table('product_categories')->insert([
            'product_id' => $productId,
            'category_id' => $categoryId,
        ]);

        return $productId;
    }

    private function createTrackedTableOrder(Table $table, Product $product): Order
    {
        $table->update(['status' => TableStatus::Occupied->value]);

        $order = Order::query()->create([
            'branch_id' => $table->branch_id,
            'table_id' => $table->id,
            'status' => OrderStatus::Preparing->value,
            'source' => OrderSource::TableQr->value,
            'type' => OrderType::DineIn->value,
            'payment_status' => OrderPaymentStatus::Unpaid->value,
            'currency' => 'JOD',
            'currency_rate' => 1,
            'subtotal' => 10,
            'total' => 10,
            'due_amount' => 10,
            'guest_count' => 2,
            'order_date' => now()->toDateString(),
        ]);

        $orderProduct = $order->products()->create([
            'product_id' => $product->id,
            'currency' => 'JOD',
            'currency_rate' => 1,
            'unit_price' => 5,
            'quantity' => 2,
            'subtotal' => 10,
            'tax_total' => 0,
            'total' => 10,
            'cost_price' => 0,
            'gross_profit' => 10,
            'status' => OrderProductStatus::Preparing->value,
        ]);

        $now = now();
        $optionId = DB::table('options')->insertGetId([
            'branch_id' => $table->branch_id,
            'name' => json_encode(['en' => 'Size', 'ar' => 'الحجم'], JSON_THROW_ON_ERROR),
            'type' => OptionType::Select->value,
            'is_required' => true,
            'is_global' => true,
            'order' => 1,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        $optionValueId = DB::table('option_values')->insertGetId([
            'branch_id' => $table->branch_id,
            'option_id' => $optionId,
            'label' => json_encode(['en' => 'Large', 'ar' => 'كبير'], JSON_THROW_ON_ERROR),
            'price' => 0,
            'price_type' => PriceType::Fixed->value,
            'order' => 1,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        $orderProductOptionId = DB::table('order_product_options')->insertGetId([
            'order_product_id' => $orderProduct->id,
            'option_id' => $optionId,
            'value' => null,
        ]);
        DB::table('order_product_option_values')->insert([
            'order_product_option_id' => $orderProductOptionId,
            'option_value_id' => $optionValueId,
            'currency' => 'JOD',
            'currency_rate' => 1,
            'price' => 0,
        ]);

        return $order;
    }

    /**
     * Build a signed public table QR API URL.
     */
    private function tableQrUrl(Table $table, string $cartId, string $action): string
    {
        return "/api/v1/table-orders/{$table->uuid}/{$action}?token={$table->table_qr_token}";
    }

    /**
     * Fake settings needed by public table QR ordering.
     */
    private function fakeTableQrSettings(): void
    {
        app()->instance('setting', new class
        {
            public function get(string $key, mixed $default = null): mixed
            {
                return match ($key) {
                    'default_currency' => 'JOD',
                    'default_date_format' => 'Y-m-d',
                    'default_locale' => 'en',
                    'default_time_format' => 'h:i A',
                    'supported_locales' => ['en', 'ar'],
                    'table_qr_allow_discount_code' => false,
                    'table_qr_allow_order_notes' => true,
                    'table_qr_auto_send_to_kitchen' => true,
                    'table_qr_cart_expires_minutes' => 120,
                    'table_qr_ordering_enabled' => true,
                    'table_qr_register_session_strategy' => 'latest_opened',
                    'table_qr_require_guest_count' => false,
                    default => $default,
                };
            }

            /**
             * @param  array<string, mixed>  $data
             * @return array<string, mixed>
             */
            public function set(array $data): array
            {
                return $data;
            }
        });
    }
}
