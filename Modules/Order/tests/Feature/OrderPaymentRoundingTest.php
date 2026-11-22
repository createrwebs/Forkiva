<?php

namespace Modules\Order\tests\Feature;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Event;
use Modules\Branch\Models\Branch;
use Modules\Order\Enums\OrderPaymentStatus;
use Modules\Order\Enums\OrderStatus;
use Modules\Order\Enums\OrderType;
use Modules\Order\Models\Order;
use Modules\Order\Services\OrderPayment\OrderPaymentServiceInterface;
use Modules\Pos\Models\PosRegister;
use Modules\Pos\Models\PosSession;
use Modules\SeatingPlan\Enums\TableMergeType;
use Modules\SeatingPlan\Enums\TableStatus;
use Modules\SeatingPlan\Models\Floor;
use Modules\SeatingPlan\Models\Table;
use Modules\SeatingPlan\Models\TableMerge;
use Modules\SeatingPlan\Models\Zone;
use Modules\User\Enums\DefaultRole;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\NotificationWorkflowTestCase;

class OrderPaymentRoundingTest extends NotificationWorkflowTestCase
{
    public static function roundingCases(): array
    {
        return [
            'reported USD totals' => ['USD', [65.8283, 65.8283, 63.7365, 35.3454], 230.75],
            'rounding decreases the USD aggregate' => ['USD', [1.2344, 1.2344], 2.46],
            'three decimal currency' => ['JOD', [1.2345, 1.2345], 2.470],
            'zero decimal currency' => ['JPY', [10.4, 10.4], 20.0],
            'half-cent boundary' => ['USD', [1.005, 4.345], 5.36],
        ];
    }

    #[DataProvider('roundingCases')]
    public function test_displayed_merged_total_can_fully_settle_every_order(string $currency, array $totals, float $expected): void
    {
        [$orders, $session, $merge] = $this->createMergedOrders($currency, $totals);
        $service = app(OrderPaymentServiceInterface::class);
        $meta = $service->getPaymentMeta($orders->first()->reference_no)['order'];

        $this->assertEqualsWithDelta($expected, $meta['grand_total']->amount(), 0.000001);
        $this->assertEqualsWithDelta($expected, $meta['due_amount']->amount(), 0.000001);
        $this->assertEquals(0, $meta['total_paid']->amount());

        $service->storePayment($orders->first()->reference_no, $this->payment($session, $meta['due_amount']->amount()));

        $paid = 0;
        foreach ($orders as $order) {
            $order->refresh();
            $this->assertSame(OrderPaymentStatus::Paid, $order->payment_status);
            $this->assertSame(OrderStatus::Completed, $order->status);
            $this->assertEquals(0, $order->due_amount->amount());
            $this->assertTrue($order->payments->every(fn ($payment) => $payment->pos_session_id === $session->id));
            $paid += $order->payments()->sum('amount');
        }
        $this->assertEqualsWithDelta($expected, $paid, 0.000001);
        $this->assertNotNull($merge->fresh()->closed_at);
    }

    public function test_partial_payment_metadata_preserves_the_payable_grand_total(): void
    {
        [$orders, $session] = $this->createMergedOrders('USD', [65.8283, 65.8283, 63.7365, 35.3454]);
        $service = app(OrderPaymentServiceInterface::class);
        $service->storePayment($orders->first()->id, $this->payment($session, 20, 'partial'));
        $meta = $service->getPaymentMeta($orders->first()->id)['order'];

        $this->assertEquals(230.75, $meta['grand_total']->amount());
        $this->assertEquals(210.75, $meta['due_amount']->amount());
        $this->assertEquals(20, $meta['total_paid']->amount());
    }

    public function test_a_real_one_cent_shortfall_is_still_rejected(): void
    {
        [$orders, $session, $merge] = $this->createMergedOrders('USD', [65.8283, 65.8283, 63.7365, 35.3454]);

        try {
            app(OrderPaymentServiceInterface::class)->storePayment($orders->first()->id, $this->payment($session, 230.74));
            $this->fail('Full payment must cover every order balance.');
        } catch (HttpException $exception) {
            $this->assertSame(400, $exception->getStatusCode());
            $this->assertStringContainsString('230.75', $exception->getMessage());
        }

        $this->assertDatabaseCount('payments', 0);
        $this->assertNull($merge->fresh()->closed_at);
    }

    private function payment(PosSession $session, float $amount, string $mode = 'full'): array
    {
        return [
            'session_id' => $session->id,
            'payment_mode' => $mode,
            'payments' => [['method' => 'card', 'amount' => $amount]],
        ];
    }

    /** @return array{Collection<int, Order>, PosSession, TableMerge} */
    private function createMergedOrders(string $currency, array $totals): array
    {
        $branch = Branch::factory()->create(['currency' => $currency, 'payment_methods' => ['card']]);
        $user = $this->createUserWithRole(DefaultRole::Admin->value, ['branch_id' => null]);
        $this->actingAs($user);
        $register = PosRegister::factory()->create(['branch_id' => $branch->id, 'is_active' => true]);
        $session = PosSession::factory()->create([
            'branch_id' => $branch->id,
            'pos_register_id' => $register->id,
            'opened_by' => $user->id,
            'opened_at' => now(),
        ]);
        $floor = Floor::factory()->create([
            'branch_id' => $branch->id,
            'name' => ['en' => 'Main floor'],
            'is_active' => true,
        ]);
        $zone = Zone::factory()->create([
            'branch_id' => $branch->id,
            'floor_id' => $floor->id,
            'name' => ['en' => 'Main zone'],
            'is_active' => true,
        ]);
        $table = Table::factory()->create([
            'branch_id' => $branch->id,
            'floor_id' => $floor->id,
            'zone_id' => $zone->id,
            'status' => TableStatus::Occupied,
        ]);
        $merge = TableMerge::query()->create([
            'branch_id' => $branch->id,
            'table_id' => $table->id,
            'type' => TableMergeType::Billing,
        ]);
        $table->update(['current_merge_id' => $merge->id]);
        $orders = collect($totals)->map(fn ($total) => Order::query()->create([
            'branch_id' => $branch->id,
            'table_id' => $table->id,
            'table_merge_id' => $merge->id,
            'status' => OrderStatus::Served,
            'type' => OrderType::DineIn,
            'payment_status' => OrderPaymentStatus::Unpaid,
            'currency' => $currency,
            'currency_rate' => 1,
            'subtotal' => $total,
            'total' => $total,
            'due_amount' => $total,
            'guest_count' => 1,
            'order_date' => now()->toDateString(),
        ]));

        // Keep external notifications and invoice listeners out of settlement assertions.
        Event::fake();

        return [$orders, $session, $merge];
    }
}
