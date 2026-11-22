<?php

namespace Modules\Dashboard\Tests\Feature;

use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Modules\Branch\Models\Branch;
use Modules\Dashboard\Enums\DashboardWidget;
use Modules\Menu\Models\Menu;
use Modules\Order\Enums\OrderPaymentStatus;
use Modules\Order\Enums\OrderProductStatus;
use Modules\Order\Enums\OrderSource;
use Modules\Order\Enums\OrderStatus;
use Modules\Order\Enums\OrderType;
use Modules\Order\Models\Order;
use Modules\Order\Models\OrderProduct;
use Modules\Product\Models\Product;
use Modules\Support\Enums\Day;
use Modules\User\Models\Permission;
use Modules\User\Models\User;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class DashboardApiTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;

    protected object $settings;

    protected function setUp(): void
    {
        parent::setUp();

        $this->settings = new class
        {
            /** @var array<string, mixed> */
            public array $values = [
                'default_currency' => 'JOD',
                'default_locale' => 'en',
                'start_of_week' => 'sunday',
            ];

            public function get(string $key, mixed $default = null): mixed
            {
                return $this->values[$key] ?? $default;
            }
        };
        app()->instance('setting', $this->settings);

        Cache::flush();
        CarbonImmutable::setTestNow('2026-08-31 10:00:00');
        $this->user = User::factory()->create(['is_active' => true]);
        $this->actingAs($this->user);
    }

    protected function tearDown(): void
    {
        CarbonImmutable::setTestNow();

        parent::tearDown();
    }

    public function test_dashboard_routes_require_a_widget_permission(): void
    {
        $this->getJson('/api/v1/dashboards/filters')->assertForbidden();
        $this->getJson('/api/v1/dashboards?widgets[]=total_users')->assertForbidden();
    }

    public function test_filters_default_to_this_year_and_expose_bounded_presets(): void
    {
        $this->grantPermission('admin.dashboards.total_users');

        $this->getJson('/api/v1/dashboards/filters')
            ->assertOk()
            ->assertJsonPath('body.defaults.from', '2026-01-01')
            ->assertJsonPath('body.defaults.to', '2026-08-31')
            ->assertJsonPath('body.meta.max_range_days', 367)
            ->assertJsonPath('body.meta.timezone', config('app.timezone'))
            ->assertJsonCount(4, 'body.presets');
    }

    public function test_dashboard_calculates_only_requested_authorized_widgets(): void
    {
        $this->grantPermission('admin.dashboards.total_users');

        $response = $this->getJson('/api/v1/dashboards?'.http_build_query([
            'from' => '2026-08-31',
            'to' => '2026-08-31',
            'widgets' => ['total_users'],
        ]));

        $response
            ->assertOk()
            ->assertJsonPath('body.meta.from', '2026-08-31')
            ->assertJsonPath('body.meta.to', '2026-08-31')
            ->assertJsonPath('body.meta.timezone', config('app.timezone'))
            ->assertJsonPath('body.widgets.total_users.authorized', true)
            ->assertJsonPath('body.widgets.total_users.data.value', 1)
            ->assertJsonMissingPath('body.widgets.total_sales');
    }

    public function test_dashboard_never_calculates_requested_widgets_without_permission(): void
    {
        $this->grantPermission('admin.dashboards.total_users');

        $this->getJson('/api/v1/dashboards?'.http_build_query([
            'from' => '2026-08-31',
            'to' => '2026-08-31',
            'widgets' => ['total_users', 'total_sales'],
        ]))
            ->assertOk()
            ->assertJsonPath('body.widgets.total_users.authorized', true)
            ->assertJsonPath('body.widgets.total_sales.authorized', false)
            ->assertJsonPath('body.widgets.total_sales.data', null);
    }

    public function test_assigned_user_cannot_read_another_branch_dashboard(): void
    {
        $this->grantPermission('admin.dashboards.total_orders');
        $assignedBranch = Branch::factory()->create(['currency' => 'JOD']);
        $otherBranch = Branch::factory()->create(['currency' => 'JOD']);
        $this->createOrder($assignedBranch, OrderStatus::Completed);
        $this->createOrder($otherBranch, OrderStatus::Completed);
        $this->user->forceFill(['branch_id' => $assignedBranch->id])->save();
        $this->actingAs($this->user->fresh());

        $this->getJson('/api/v1/dashboards?'.http_build_query([
            'branch_id' => $otherBranch->id,
            'from' => '2026-08-31',
            'to' => '2026-08-31',
            'widgets' => ['total_orders'],
        ]))->assertNotFound();

        $this->getJson('/api/v1/dashboards?'.http_build_query([
            'from' => '2026-08-31',
            'to' => '2026-08-31',
            'widgets' => ['total_orders'],
        ]))
            ->assertOk()
            ->assertJsonPath('body.meta.branch_id', $assignedBranch->id)
            ->assertJsonPath('body.widgets.total_orders.data.value', 1);
    }

    public function test_dashboard_rejects_oversized_and_unknown_widget_requests(): void
    {
        $this->grantPermission('admin.dashboards.total_users');

        $this->getJson('/api/v1/dashboards?'.http_build_query([
            'from' => '2025-01-01',
            'to' => '2026-08-31',
            'widgets' => ['total_users'],
        ]))->assertUnprocessable();

        $this->getJson('/api/v1/dashboards?'.http_build_query([
            'from' => '2026-08-31',
            'to' => '2026-08-31',
            'widgets' => ['not_a_widget'],
        ]))->assertUnprocessable();
    }

    public function test_every_widget_executes_against_the_supported_test_database(): void
    {
        $widgets = collect(DashboardWidget::cases());

        foreach ($widgets->map(fn (DashboardWidget $widget) => $widget->permission())->unique() as $permission) {
            $this->grantPermission($permission);
        }

        $response = $this->getJson('/api/v1/dashboards?'.http_build_query([
            'from' => '2026-08-31',
            'to' => '2026-08-31',
            'widgets' => $widgets->pluck('value')->all(),
        ]));

        $response->assertOk();
        $this->assertCount(27, $response->json('body.widgets'));

        foreach ($widgets as $widget) {
            $response->assertJsonPath("body.widgets.{$widget->value}.authorized", true);
        }
    }

    public function test_nullable_widget_options_use_safe_defaults(): void
    {
        $this->grantPermission('admin.dashboards.sales_analytics');
        $this->grantPermission('admin.dashboards.hourly_sales_trend');

        $response = $this->json('GET', '/api/v1/dashboards', [
            'from' => '2026-08-20',
            'to' => '2026-08-20',
            'widgets' => ['sales_analytics', 'hourly_sales_trend'],
            'widget_options' => [
                'sales_analytics' => ['granularity' => null],
                'hourly_sales_trend' => ['date' => null],
            ],
        ]);

        $response
            ->assertOk()
            ->assertJsonPath('body.meta.granularity', 'hour')
            ->assertJsonPath('body.widgets.sales_analytics.data.granularity', 'hour')
            ->assertJsonPath('body.widgets.hourly_sales_trend.data.date', '2026-08-20');
    }

    public function test_sales_comparison_series_always_match_current_labels(): void
    {
        $this->grantPermission('admin.dashboards.sales_analytics');

        $response = $this->getJson('/api/v1/dashboards?'.http_build_query([
            'from' => '2026-07-17',
            'to' => '2026-08-31',
            'widgets' => ['sales_analytics'],
        ]))->assertOk();

        $labels = $response->json('body.widgets.sales_analytics.data.labels');

        $this->assertCount(count($labels), $response->json('body.widgets.sales_analytics.data.sales'));
        $this->assertCount(count($labels), $response->json('body.widgets.sales_analytics.data.orders'));
        $this->assertCount(count($labels), $response->json('body.widgets.sales_analytics.data.previous_sales'));
        $this->assertCount(count($labels), $response->json('body.widgets.sales_analytics.data.previous_orders'));
    }

    public function test_active_orders_are_not_stale_between_requests(): void
    {
        $this->grantPermission('admin.dashboards.total_active_orders');
        $branch = Branch::factory()->create(['currency' => 'JOD']);
        $query = http_build_query([
            'branch_id' => $branch->id,
            'from' => '2026-08-31',
            'to' => '2026-08-31',
            'widgets' => ['total_active_orders'],
        ]);

        $this->getJson('/api/v1/dashboards?'.$query)
            ->assertOk()
            ->assertJsonPath('body.widgets.total_active_orders.data.value', 0);

        $this->createOrder($branch, OrderStatus::Pending);

        $this->getJson('/api/v1/dashboards?'.$query)
            ->assertOk()
            ->assertJsonPath('body.widgets.total_active_orders.data.value', 1);
    }

    public function test_order_sources_are_normalized_into_one_pos_bucket(): void
    {
        $this->grantPermission('admin.dashboards.order_source_distribution');
        $branch = Branch::factory()->create(['currency' => 'JOD']);
        $legacyPosOrder = $this->createOrder($branch, OrderStatus::Completed);
        $this->createOrder($branch, OrderStatus::Completed, ['source' => OrderSource::TableQr]);

        DB::table('orders')
            ->where('id', $legacyPosOrder->id)
            ->update(['source' => 'pos']);
        $this->createOrder($branch, OrderStatus::Completed);

        $response = $this->getJson('/api/v1/dashboards?'.http_build_query([
            'branch_id' => $branch->id,
            'from' => '2026-08-31',
            'to' => '2026-08-31',
            'widgets' => ['order_source_distribution'],
        ]))->assertOk();
        $items = collect($response->json('body.widgets.order_source_distribution.data'));

        $this->assertCount(2, $items);
        $this->assertSame(2, $items->firstWhere('id', 'pos')['value']);
        $this->assertSame(1, $items->firstWhere('id', OrderSource::TableQr->value)['value']);
    }

    public function test_top_products_use_order_scope_and_return_aggregate_values(): void
    {
        $this->grantPermission('admin.dashboards.top_selling_products');
        $branch = Branch::factory()->create(['currency' => 'JOD']);
        $menu = Menu::factory()->create(['branch_id' => $branch->id]);
        $product = Product::factory()->create([
            'menu_id' => $menu->id,
            'name' => ['en' => 'Dashboard product'],
        ]);
        $order = $this->createOrder($branch, OrderStatus::Completed);
        OrderProduct::query()->create([
            'order_id' => $order->id,
            'product_id' => $product->id,
            'created_by' => $this->user->id,
            'currency' => 'JOD',
            'currency_rate' => 1,
            'unit_price' => 10,
            'quantity' => 3,
            'subtotal' => 30,
            'tax_total' => 0,
            'total' => 30,
            'cost_price' => 12,
            'gross_profit' => 18,
            'status' => OrderProductStatus::Served,
        ]);

        $this->getJson('/api/v1/dashboards?'.http_build_query([
            'branch_id' => $branch->id,
            'from' => '2026-08-31',
            'to' => '2026-08-31',
            'widgets' => ['top_selling_products'],
        ]))
            ->assertOk()
            ->assertJsonPath('body.widgets.top_selling_products.data.0.id', $product->id)
            ->assertJsonPath('body.widgets.top_selling_products.data.0.name', 'Dashboard product')
            ->assertJsonPath('body.widgets.top_selling_products.data.0.total_quantity', 3)
            ->assertJsonPath('body.widgets.top_selling_products.data.0.total_sales.amount', 30);
    }

    public function test_fulfillment_rate_counts_only_completed_orders(): void
    {
        $this->grantPermission('admin.dashboards.fulfillment_health');
        $branch = Branch::factory()->create(['currency' => 'JOD']);

        foreach ([
            OrderStatus::Completed,
            OrderStatus::Completed,
            OrderStatus::Pending,
            OrderStatus::Cancelled,
            OrderStatus::Merged,
        ] as $status) {
            $this->createOrder($branch, $status);
        }

        $this->getJson('/api/v1/dashboards?'.http_build_query([
            'branch_id' => $branch->id,
            'from' => '2026-08-31',
            'to' => '2026-08-31',
            'widgets' => ['fulfillment_health'],
        ]))
            ->assertOk()
            ->assertJsonPath('body.widgets.fulfillment_health.data.cancelled_or_refunded', 1)
            ->assertJsonPath('body.widgets.fulfillment_health.data.completion_rate', 50);
    }

    public function test_dashboard_reporting_indexes_are_present(): void
    {
        $this->assertContains(
            'orders_dashboard_created_branch_status_idx',
            collect(Schema::getIndexes('orders'))->pluck('name')->all(),
        );
        $this->assertContains(
            'payments_dashboard_created_branch_voided_method_idx',
            collect(Schema::getIndexes('payments'))->pluck('name')->all(),
        );
        $this->assertContains(
            'cash_movements_dashboard_occurred_branch_direction_idx',
            collect(Schema::getIndexes('pos_cash_movements'))->pluck('name')->all(),
        );
    }

    public function test_profit_aggregates_do_not_use_order_money_accessors(): void
    {
        $this->grantPermission('admin.dashboards.gross_profit_margin');
        $branch = Branch::factory()->create(['currency' => 'JOD']);

        Order::query()->create([
            'branch_id' => $branch->id,
            'status' => OrderStatus::Completed,
            'type' => OrderType::DineIn,
            'payment_status' => OrderPaymentStatus::Paid,
            'currency' => 'JOD',
            'currency_rate' => 1,
            'subtotal' => 40,
            'total' => 40,
            'cost_price' => 25,
            'gross_profit' => 15,
            'due_amount' => 0,
            'guest_count' => 1,
            'order_date' => '2026-08-31',
            'created_at' => '2026-08-31 09:00:00',
        ]);

        $this->getJson('/api/v1/dashboards?'.http_build_query([
            'from' => '2026-08-31',
            'to' => '2026-08-31',
            'widgets' => ['gross_profit_margin'],
        ]))
            ->assertOk()
            ->assertJsonPath('body.widgets.gross_profit_margin.data.margin_percent', 37.5)
            ->assertJsonPath('body.widgets.gross_profit_margin.data.gross_profit.amount', 15)
            ->assertJsonPath('body.widgets.gross_profit_margin.data.cost.amount', 25);
    }

    public function test_guest_collection_payment_and_weekday_widgets_use_scoped_order_aggregates(): void
    {
        foreach ([
            'admin.dashboards.outstanding_balance',
            'admin.dashboards.total_guests',
            'admin.dashboards.revenue_per_guest',
            'admin.dashboards.payment_status_distribution',
            'admin.dashboards.sales_by_weekday',
        ] as $permission) {
            $this->grantPermission($permission);
        }

        $branch = Branch::factory()->create(['currency' => 'JOD']);
        $this->createOrder($branch, OrderStatus::Completed, [
            'payment_status' => OrderPaymentStatus::Paid,
            'total' => 60,
            'due_amount' => 20,
            'guest_count' => 3,
            'order_date' => '2026-08-31',
            'created_at' => '2026-08-31 09:00:00',
        ]);
        $this->createOrder($branch, OrderStatus::Completed, [
            'payment_status' => OrderPaymentStatus::PartiallyPaid,
            'total' => 40,
            'due_amount' => 10,
            'guest_count' => 1,
            'created_at' => '2026-09-01 12:00:00',
        ]);
        $this->createOrder($branch, OrderStatus::Completed, [
            'payment_status' => OrderPaymentStatus::Unpaid,
            'total' => 20,
            'due_amount' => 20,
            'guest_count' => 1,
            'created_at' => '2026-09-01 13:00:00',
        ]);

        $response = $this->getJson('/api/v1/dashboards?'.http_build_query([
            'branch_id' => $branch->id,
            'from' => '2026-08-31',
            'to' => '2026-09-01',
            'widgets' => [
                'outstanding_balance',
                'total_guests',
                'revenue_per_guest',
                'payment_status_distribution',
                'sales_by_weekday',
            ],
        ]))->assertOk();

        $response
            ->assertJsonPath('body.widgets.outstanding_balance.data.value.amount', 50)
            ->assertJsonPath('body.widgets.total_guests.data.value', 5)
            ->assertJsonPath('body.widgets.revenue_per_guest.data.value.amount', 24)
            ->assertJsonPath('body.widgets.sales_by_weekday.data.labels.0', Day::Sunday->trans())
            ->assertJsonPath('body.widgets.sales_by_weekday.data.labels.1', Day::Monday->trans())
            ->assertJsonPath('body.widgets.sales_by_weekday.data.labels.6', Day::Saturday->trans())
            ->assertJsonPath('body.widgets.sales_by_weekday.data.sales.0', 0)
            ->assertJsonPath('body.widgets.sales_by_weekday.data.orders.0', 0)
            ->assertJsonPath('body.widgets.sales_by_weekday.data.sales.1', 60)
            ->assertJsonPath('body.widgets.sales_by_weekday.data.orders.1', 1)
            ->assertJsonPath('body.widgets.sales_by_weekday.data.sales.2', 60)
            ->assertJsonPath('body.widgets.sales_by_weekday.data.orders.2', 2);

        $this->settings->values['start_of_week'] = Day::Wednesday->value;

        $this->getJson('/api/v1/dashboards?'.http_build_query([
            'branch_id' => $branch->id,
            'from' => '2026-08-31',
            'to' => '2026-09-01',
            'widgets' => ['sales_by_weekday'],
        ]))
            ->assertOk()
            ->assertJsonPath('body.widgets.sales_by_weekday.data.labels.0', Day::Wednesday->trans())
            ->assertJsonPath('body.widgets.sales_by_weekday.data.labels.5', Day::Monday->trans())
            ->assertJsonPath('body.widgets.sales_by_weekday.data.labels.6', Day::Tuesday->trans())
            ->assertJsonPath('body.widgets.sales_by_weekday.data.sales.5', 60)
            ->assertJsonPath('body.widgets.sales_by_weekday.data.orders.5', 1)
            ->assertJsonPath('body.widgets.sales_by_weekday.data.sales.6', 60)
            ->assertJsonPath('body.widgets.sales_by_weekday.data.orders.6', 2);

        $paymentStatuses = collect(
            $response->json('body.widgets.payment_status_distribution.data'),
        )->pluck('value', 'id');

        $this->assertSame(1, $paymentStatuses->get(OrderPaymentStatus::Paid->value));
        $this->assertSame(1, $paymentStatuses->get(OrderPaymentStatus::PartiallyPaid->value));
        $this->assertSame(1, $paymentStatuses->get(OrderPaymentStatus::Unpaid->value));
    }

    /**
     * Create the smallest valid dashboard order for an exact branch and status.
     *
     * @param  array<string, mixed>  $attributes
     */
    protected function createOrder(
        Branch $branch,
        OrderStatus $status,
        array $attributes = [],
    ): Order {
        return Order::query()->create([
            'branch_id' => $branch->id,
            'status' => $status,
            'type' => OrderType::DineIn,
            'payment_status' => OrderPaymentStatus::Paid,
            'currency' => 'JOD',
            'currency_rate' => 1,
            'subtotal' => 10,
            'total' => 10,
            'cost_price' => 5,
            'gross_profit' => 5,
            'due_amount' => 0,
            'guest_count' => 1,
            'order_date' => '2026-08-31',
            'created_at' => '2026-08-31 09:00:00',
            ...$attributes,
        ]);
    }

    protected function grantPermission(string $permission): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();
        Permission::query()->firstOrCreate([
            'name' => $permission,
            'guard_name' => 'api',
        ]);
        $this->user->givePermissionTo($permission);
    }
}
