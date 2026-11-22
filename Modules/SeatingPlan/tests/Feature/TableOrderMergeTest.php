<?php

namespace Modules\SeatingPlan\tests\Feature;

use Illuminate\Support\Facades\Event;
use Modules\Branch\Models\Branch;
use Modules\Discount\Models\Discount;
use Modules\Menu\Models\Menu;
use Modules\Order\Enums\DiscountType;
use Modules\Order\Enums\OrderPaymentStatus;
use Modules\Order\Enums\OrderSource;
use Modules\Order\Enums\OrderStatus;
use Modules\Order\Enums\OrderType;
use Modules\Order\Models\Order;
use Modules\Order\Models\OrderDiscount;
use Modules\Product\Models\Product;
use Modules\SeatingPlan\Enums\TableMergeType;
use Modules\SeatingPlan\Enums\TableStatus;
use Modules\SeatingPlan\Models\Floor;
use Modules\SeatingPlan\Models\Table;
use Modules\SeatingPlan\Models\Zone;
use Modules\SeatingPlan\Services\TableViewer\TableViewerServiceInterface;
use Modules\Support\Enums\PriceType;
use Modules\User\Enums\DefaultRole;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\NotificationWorkflowTestCase;

class TableOrderMergeTest extends NotificationWorkflowTestCase
{
    public static function discountCases(): array
    {
        return [
            'neither order has a discount' => [false, false],
            'source discount is transferred' => [false, true],
            'destination discount is retained' => [true, true],
        ];
    }

    #[DataProvider('discountCases')]
    public function test_order_merge_handles_optional_discounts(bool $mainDiscount, bool $sourceDiscount): void
    {
        $branch = Branch::factory()->create(['currency' => 'JOD']);
        $admin = $this->createUserWithRole(DefaultRole::Admin->value, ['branch_id' => null]);
        $this->actingAs($admin);
        $floor = Floor::factory()->create(['branch_id' => $branch->id, 'name' => ['en' => 'Main'], 'is_active' => true]);
        $zone = Zone::factory()->create(['branch_id' => $branch->id, 'floor_id' => $floor->id, 'name' => ['en' => 'Indoor'], 'is_active' => true]);
        $tables = Table::factory()->count(2)->create([
            'branch_id' => $branch->id,
            'floor_id' => $floor->id,
            'zone_id' => $zone->id,
            'status' => TableStatus::Occupied,
        ]);
        $main = $this->order($tables[0], 'Main order');
        $source = $this->order($tables[1], 'Source order');
        $retained = $mainDiscount ? $this->discount($main) : null;
        $transferred = $sourceDiscount ? $this->discount($source) : null;

        // External notifications are unrelated to merge persistence.
        Event::fake();
        app(TableViewerServiceInterface::class)->merge($tables[0]->id, [
            'branch_id' => $branch->id,
            'table_ids' => [$tables[1]->id],
            'type' => TableMergeType::Order->value,
        ]);

        $main->refresh();
        $source->refresh();
        $this->assertSame(OrderStatus::Merged, $source->status);
        $this->assertEquals($main->id, $source->merged_into_order_id);
        $this->assertEquals(4, $main->guest_count);
        $this->assertStringContainsString('Main order', $main->notes);
        $this->assertStringContainsString('Source order', $main->notes);
        $this->assertSame(TableStatus::Merged, $tables[1]->fresh()->status);
        $this->assertEquals($tables[0]->fresh()->current_merge_id, $tables[1]->fresh()->current_merge_id);
        $this->assertSame(0, OrderDiscount::where('order_id', $source->id)->count());
        $this->assertEquals(($retained ?? $transferred)?->id, $main->discount?->id);
        $this->assertSame($mainDiscount || $sourceDiscount ? 1 : 0, OrderDiscount::count());
        $this->assertSame(2, $main->products()->count());
        $this->assertSame(0, $source->products()->count());
        $this->assertEquals(20, $main->subtotal->amount());
        $this->assertEquals($mainDiscount || $sourceDiscount ? 15 : 20, $main->total->amount());
        if ($mainDiscount || $sourceDiscount) {
            $this->assertEquals(5, $main->discount->amount->amount());
        }
    }

    private function order(Table $table, string $notes): Order
    {
        $order = Order::query()->create([
            'branch_id' => $table->branch_id,
            'table_id' => $table->id,
            'status' => OrderStatus::Preparing,
            'source' => OrderSource::TableQr,
            'type' => OrderType::DineIn,
            'payment_status' => OrderPaymentStatus::Unpaid,
            'currency' => 'JOD',
            'currency_rate' => 1,
            'subtotal' => 0,
            'total' => 0,
            'due_amount' => 0,
            'guest_count' => 2,
            'notes' => $notes,
            'order_date' => now()->toDateString(),
        ]);

        $menu = Menu::factory()->create(['branch_id' => $table->branch_id]);
        $product = Product::factory()->create(['menu_id' => $menu->id]);
        $order->products()->create([
            'product_id' => $product->id,
            'currency' => 'JOD',
            'currency_rate' => 1,
            'unit_price' => 10,
            'quantity' => 1,
            'subtotal' => 10,
            'tax_total' => 0,
            'total' => 10,
            'cost_price' => 0,
            'gross_profit' => 10,
        ]);

        return $order;
    }

    private function discount(Order $order): OrderDiscount
    {
        $discount = Discount::factory()->create([
            'branch_id' => $order->branch_id,
            'is_active' => true,
            'type' => PriceType::Fixed,
            'value' => 5,
        ]);

        return $order->discount()->create([
            'discountable_type' => Discount::class,
            'discountable_id' => $discount->id,
            'type' => DiscountType::Discount,
            'currency' => 'JOD',
            'currency_rate' => 1,
            'amount' => 0,
        ]);
    }
}
