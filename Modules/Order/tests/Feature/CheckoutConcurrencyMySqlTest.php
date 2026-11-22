<?php

namespace Modules\Order\Tests\Feature;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Modules\Order\Enums\OrderType;
use Modules\Order\Models\Order;
use Modules\Order\Support\OrderRevision;
use Modules\Order\Support\OrderWriteLock;
use Modules\Product\Models\Product;
use Modules\SeatingPlan\Enums\TableShape;
use Modules\SeatingPlan\Enums\TableStatus;
use Modules\SeatingPlan\Models\Table;
use Modules\SeatingPlan\Services\TableQrOrder\TableQrOrderServiceInterface;
use Tests\Concerns\InteractsWithAccountingMySql;
use Tests\TestCase;

class CheckoutConcurrencyMySqlTest extends TestCase
{
    use InteractsWithAccountingMySql;

    private bool $committedForConcurrency = false;

    protected function setUp(): void
    {
        parent::setUp();
        $this->requireAccountingMySql(true);
        $this->rebuildAccountingMySqlSchema();
        Schema::disableForeignKeyConstraints();
        try {
            $this->artisan('migrate', ['--path' => array_map(fn ($module) => module_path($module, 'database/migrations'), ['Cart', 'Loyalty', 'Voucher', 'Media']), '--realpath' => true, '--force' => true])->assertExitCode(0);
        } finally {
            Schema::enableForeignKeyConstraints();
        }
        $this->app->instance(\Modules\Core\Contracts\DeliveryFeatureInterface::class, new \Modules\Core\Services\Delivery\NullDeliveryFeatureService);
        $this->fakeTableQrSettings();
        Notification::fake();
    }

    private function context(): array
    {
        [$table,$product] = $this->tableQrFixture();
        $user = \Modules\User\Models\User::factory()->create(['is_active' => true]);
        $register = \Modules\Pos\Models\PosRegister::factory()->create(['branch_id' => $table->branch_id, 'is_active' => true]);
        \Modules\Pos\Models\PosSession::factory()->create(['branch_id' => $table->branch_id, 'pos_register_id' => $register->id, 'opened_by' => $user->id, 'opened_at' => now()]);
        $input = ['items' => [['line_id' => 'burger', 'product_id' => $product->id, 'qty' => 1, 'options' => []]], 'guest_count' => 1];
        $service = app(TableQrOrderServiceInterface::class);
        $input['quote_token'] = $service->quote($table->uuid, $table->table_qr_token, $input)['quote_token'];
        $input['idempotency_key'] = (string) Str::uuid();

        return [$table, $input];
    }

    public function test_parallel_retries_commit_one_order_and_one_receipt(): void
    {
        [$table,$input] = $this->context();
        $results = $this->runAccountingMySqlWorkers(function () use ($table, $input): string {
            return (string) app(TableQrOrderServiceInterface::class)->submit($table->uuid, $table->table_qr_token, $input, Request::create('/test-submit'))['id'];
        });
        $this->assertCount(1, $results->unique(), $results->implode(' | '));
        $this->assertFalse(str_starts_with($results->first(), 'error:'), $results->implode(' | '));
        $this->assertDatabaseCount('orders', 1);
        $this->assertDatabaseCount('order_submissions', 1);
        $this->assertEquals(1, Order::firstOrFail()->products()->sum('quantity'));
    }

    public function test_parallel_table_requests_requote_then_append_without_a_second_active_order(): void
    {
        [$table,$input] = $this->context();
        $results = $this->runAccountingMySqlWorkers(function ($worker) use ($table, $input): string {
            $request = $input;
            $request['idempotency_key'] = (string) Str::uuid();
            $service = app(TableQrOrderServiceInterface::class);
            try {
                $service->submit($table->uuid, $table->table_qr_token, $request, Request::create('/test-submit'));

                return 'saved';
            } catch (\Illuminate\Http\Exceptions\HttpResponseException $e) {
                return (string) $e->getResponse()->getStatusCode();
            }
        });
        $this->assertSame(['409', 'saved'], $results->sort()->values()->all(), $results->implode(' | '));
        $this->assertDatabaseCount('orders', 1);
        $next = ['items' => $input['items'], 'guest_count' => 1];
        $service = app(TableQrOrderServiceInterface::class);
        $next['quote_token'] = $service->quote($table->uuid, $table->table_qr_token, $next)['quote_token'];
        $next['idempotency_key'] = (string) Str::uuid();
        $service->submit($table->uuid, $table->table_qr_token, $next, Request::create('/test-submit'));
        $this->assertDatabaseCount('orders', 1);
        $this->assertEquals(2, Order::firstOrFail()->products()->sum('quantity'));
    }

    public function test_order_write_lock_allows_only_one_editor_to_use_a_revision(): void
    {
        [$table,$input] = $this->context();
        $id = app(TableQrOrderServiceInterface::class)->submit($table->uuid, $table->table_qr_token, $input, Request::create('/test-submit'))['id'];
        $version = OrderRevision::for(Order::findOrFail($id));
        $results = $this->runAccountingMySqlWorkers(fn ($worker) => OrderWriteLock::run($id, function () use ($id, $version, $worker): string {
            $order = Order::findOrFail($id);
            if (OrderRevision::for($order) !== $version) {
                return 'conflict';
            }
            $order->update(['notes' => 'editor '.$worker]);

            return 'saved';
        }));
        $this->assertSame(['conflict', 'saved'], $results->sort()->values()->all(), $results->implode(' | '));
    }

    public function test_payment_commits_before_waiting_editor_rechecks_its_revision(): void
    {
        [$table,$input] = $this->context();
        $id = app(TableQrOrderServiceInterface::class)->submit($table->uuid, $table->table_qr_token, $input, Request::create('/test-submit'))['id'];
        $order = Order::findOrFail($id);
        $order->update(['table_id' => null, 'type' => OrderType::Takeaway]);
        $version = OrderRevision::for($order);
        $session = \Modules\Pos\Models\PosSession::firstOrFail();
        $actor = \Modules\User\Models\User::findOrFail($session->opened_by);
        $this->actingAs($actor);
        // Invoice/financial event integration is covered by PosCartCheckoutFlowTest and the Accounting gates.
        \Illuminate\Support\Facades\Event::fake([\Modules\Order\Events\OrderPaid::class]);
        $signal = sys_get_temp_dir().'/checkout-payment-'.Str::uuid();
        try {
            $results = $this->runAccountingMySqlWorkers(function ($worker) use ($id, $version, $session, $order, $signal): string {
                if ($worker === 0) {
                    return OrderWriteLock::run($id, function () use ($id, $session, $order, $signal): string {
                        file_put_contents($signal, 'locked');
                        usleep(100_000);
                        app(\Modules\Order\Services\OrderPayment\OrderPaymentServiceInterface::class)->storePayment($id, [
                            'session_id' => $session->id, 'register_id' => $session->pos_register_id, 'payment_mode' => 'full',
                            'payments' => [['method' => 'card', 'amount' => $order->total->amount()]], 'with_print' => false,
                        ]);

                        return 'paid';
                    });
                }
                $deadline = microtime(true) + 5;
                while (! file_exists($signal) && microtime(true) < $deadline) {
                    usleep(1_000);
                }

                return OrderWriteLock::run($id, function () use ($id, $version): string {
                    if (OrderRevision::for(Order::findOrFail($id)) !== $version) {
                        return 'conflict';
                    }

                    return 'stale_edit_allowed';
                });
            });
            $this->assertSame(['conflict', 'paid'], $results->sort()->values()->all(), $results->implode(' | '));
            $this->assertSame(\Modules\Order\Enums\OrderPaymentStatus::Paid, Order::findOrFail($id)->payment_status);
            $this->assertEquals($order->total->amount(), Order::findOrFail($id)->payments()->sum('amount'));
        } finally {
            if (file_exists($signal)) {
                unlink($signal);
            }
        }
    }

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
