<?php

namespace Modules\DailyFinance\Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Str;
use Modules\Branch\Models\Branch;
use Modules\Core\Contracts\FinancialAccountLookupInterface;
use Modules\Core\Contracts\FinancialExpenseSummaryReaderInterface;
use Modules\Core\Services\Accounting\NullFinancialAccountLookup;
use Modules\Core\Services\Accounting\NullFinancialExpenseSummaryReader;
use Modules\DailyFinance\Models\DayClosing;
use Modules\DailyFinance\Models\PaymentSettlementReceipt;
use Modules\Order\Enums\OrderPaymentStatus;
use Modules\Order\Enums\OrderStatus;
use Modules\Order\Enums\OrderType;
use Modules\Order\Models\Order;
use Modules\Payment\Enums\PaymentMethod;
use Modules\Payment\Enums\PaymentType;
use Modules\Pos\Enums\PosSessionStatus;
use Modules\Pos\Events\ClosePosSession;
use Modules\Pos\Models\PosRegister;
use Modules\Pos\Models\PosSession;
use Modules\Pos\Services\PosCashMovement\PosCashMovementServiceInterface;
use Modules\User\Models\Permission;
use Modules\User\Models\User;
use Tests\TestCase;

class DailyFinanceWorkflowTest extends TestCase
{
    use RefreshDatabase;

    public function test_reviewed_pos_close_custody_day_close_and_bank_credits_preserve_source_totals(): void
    {
        config(['app.timezone' => 'UTC']);
        $this->travelTo(now()->setDate(2026, 10, 13)->setTime(12, 0));
        Event::fake([ClosePosSession::class]);
        app()->instance(FinancialAccountLookupInterface::class, new NullFinancialAccountLookup);
        app()->instance(FinancialExpenseSummaryReaderInterface::class, new NullFinancialExpenseSummaryReader);
        $branch = Branch::factory()->create(['currency' => 'JOD', 'timezone' => 'Asia/Amman']);
        $cashier = User::factory()->create(['branch_id' => $branch->id, 'is_active' => true]);
        $receiver = User::factory()->create(['branch_id' => $branch->id, 'is_active' => true]);
        $permissions = [
            'admin.daily_finance.index',
            'admin.orders.index',
            'admin.payments.index',
            'admin.pos_cash_movements.index',
            'admin.pos_sessions.index',
            'admin.pos_sessions.show',
            'admin.pos_sessions.close',
            'admin.daily_finance_handovers.index',
            'admin.daily_finance_handovers.create',
            'admin.daily_finance_handovers.receive',
            'admin.daily_finance_closings.index',
            'admin.daily_finance_closings.close',
            'admin.daily_finance_settlements.index',
            'admin.daily_finance_settlements.create',
            'admin.daily_finance_settlements.receive',
        ];
        foreach ([$cashier, $receiver] as $actor) {
            foreach ($permissions as $permission) {
                $actor->givePermissionTo(Permission::findOrCreate($permission, 'api'));
            }
        }
        $this->actingAs($cashier);
        $register = PosRegister::factory()->create(['branch_id' => $branch->id, 'is_active' => true]);
        $session = PosSession::factory()->create([
            'branch_id' => $branch->id,
            'pos_register_id' => $register->id,
            'opened_by' => $cashier->id,
            'opened_at' => now()->subHour(),
            'opening_float' => '5.000',
            'status' => PosSessionStatus::Open,
        ]);
        $order = Order::create([
            'branch_id' => $branch->id,
            'pos_register_id' => $register->id,
            'pos_session_id' => $session->id,
            'status' => OrderStatus::Completed,
            'type' => OrderType::Takeaway,
            'payment_status' => OrderPaymentStatus::Paid,
            'currency' => 'JOD',
            'currency_rate' => 1,
            'subtotal' => '30.345',
            'total' => '30.345',
            'due_amount' => '0',
            'order_date' => '2026-10-13',
        ]);
        foreach ([[PaymentMethod::Cash, '10.345'], [PaymentMethod::Card, '20.000']] as [$method, $amount]) {
            $payment = $order->payments()->create([
                'branch_id' => $branch->id,
                'pos_session_id' => $session->id,
                'order_reference_no' => $order->reference_no,
                'method' => $method,
                'type' => PaymentType::Payment,
                'amount' => $amount,
                'currency' => 'JOD',
                'currency_rate' => 1,
                'received_at' => now(),
            ]);
            if ($method === PaymentMethod::Cash) {
                app(PosCashMovementServiceInterface::class)->sale($session, 10.345, $order->id, $payment->id);
            }
        }
        $sources = $order->payments()->orderBy('id')->get()->map->getAttributes()->all();
        $dayQuery = http_build_query(['branch_id' => $branch->id, 'date' => '2026-10-13']);
        $closingUrl = '/api/v1/daily-finance/closings';
        $this->getJson($closingUrl.'/review?'.$dayQuery)->assertOk()->assertJsonPath('body.blockers.0', 'open_sessions');

        $posUrl = '/api/v1/pos/sessions/'.$session->id;
        $token = $this->postJson($posUrl.'/closing-preview', ['declared_cash' => '15.345'])
            ->assertOk()->assertJsonPath('body.review.can_close', true)->json('body.review.token');
        $this->putJson($posUrl.'/close', ['declared_cash' => '15.345', 'review_token' => $token])->assertOk();
        $this->assertSame(PosSessionStatus::Closed, $session->fresh()->status);
        $posSnapshot = $session->fresh()->getAttributes();

        $handoverUrl = '/api/v1/daily-finance/sessions/'.$session->id.'/handovers';
        $handoverPayload = [
            'amount' => '15.345',
            'receiver_id' => $receiver->id,
            'request_key' => (string) Str::uuid(),
        ];
        $handoverId = $this->postJson($handoverUrl, $handoverPayload)->assertOk()->json('body.id');
        $this->postJson($handoverUrl, $handoverPayload)->assertOk()->assertJsonPath('body.id', $handoverId);
        $this->getJson($closingUrl.'/review?'.$dayQuery)->assertOk()->assertJsonPath('body.blockers.0', 'pending_handovers');
        $this->actingAs($receiver);
        $this->postJson($handoverUrl.'/'.$handoverId.'/receive', ['received_amount' => '15.345'])->assertOk();
        $this->postJson($handoverUrl.'/'.$handoverId.'/receive', ['received_amount' => '15.345'])->assertOk();
        $this->getJson('/api/v1/daily-finance/handovers/summary?branch_id='.$branch->id)->assertOk()
            ->assertJsonPath('body.unhanded_sessions_count', 0)->assertJsonPath('body.pending_handovers_count', 0);

        $review = $this->getJson($closingUrl.'/review?'.$dayQuery)->assertOk()
            ->assertJsonPath('body.blockers', [])->assertJsonPath('body.warnings', [])->json('body');
        $closePayload = [
            'branch_id' => $branch->id,
            'date' => '2026-10-13',
            'review_hash' => $review['review_hash'],
            'request_key' => (string) Str::uuid(),
        ];
        $closingId = $this->postJson($closingUrl, $closePayload)->assertOk()
            ->assertJsonPath('body.summary.sales.total.amount', 30.345)
            ->assertJsonPath('body.summary.payments.collections.amount', 30.345)->json('body.id');
        $this->postJson($closingUrl, $closePayload)->assertOk()->assertJsonPath('body.id', $closingId);
        $closingSnapshot = DayClosing::findOrFail($closingId)->snapshot;

        $settlementUrl = '/api/v1/daily-finance/settlements';
        $candidate = $this->getJson($settlementUrl.'/candidates?'.http_build_query([
            'branch_id' => $branch->id,
            'method' => 'card',
            'from' => '2026-10-13',
            'to' => '2026-10-13',
        ]))->assertOk()->assertJsonCount(1, 'body.data')->json('body.data.0');
        $settlementPayload = [
            'branch_id' => $branch->id,
            'method' => 'card',
            'payment_ids' => [$candidate['id']],
            'source_hashes' => [$candidate['id'] => $candidate['source_hash']],
            'fees' => '0.345',
            'destination_name' => 'Main bank',
            'reference' => 'BATCH-TEST',
            'request_key' => (string) Str::uuid(),
        ];
        $settlementId = $this->postJson($settlementUrl, $settlementPayload)->assertOk()
            ->assertJsonPath('body.expected_decimal', '19.6550')->json('body.id');
        $this->postJson($settlementUrl, $settlementPayload)->assertOk()->assertJsonPath('body.id', $settlementId);
        $credit = [
            'branch_id' => $branch->id,
            'amount' => '10.000',
            'received_date' => '2026-10-13',
            'reference' => 'BANK-TEST-1',
            'is_final' => false,
            'request_key' => (string) Str::uuid(),
        ];
        $this->postJson($settlementUrl.'/'.$settlementId.'/receive', $credit)->assertOk()
            ->assertJsonPath('body.status.id', 'partial')->assertJsonPath('body.remaining.amount', 9.655);
        $this->postJson($settlementUrl.'/'.$settlementId.'/receive', $credit)->assertOk()->assertJsonCount(1, 'body.receipts');
        $this->postJson($settlementUrl.'/'.$settlementId.'/receive', [
            ...$credit,
            'amount' => '9.655',
            'reference' => 'BANK-TEST-2',
            'is_final' => true,
            'request_key' => (string) Str::uuid(),
        ])->assertOk()->assertJsonPath('body.status.id', 'settled')->assertJsonPath('body.variance.amount', 0);
        $this->getJson($settlementUrl.'/summary?branch_id='.$branch->id)->assertOk()->assertJsonPath('body.open_count', 0);

        $this->assertSame($sources, $order->payments()->orderBy('id')->get()->map->getAttributes()->all());
        $this->assertSame($posSnapshot, $session->fresh()->getAttributes());
        $this->assertSame($closingSnapshot, DayClosing::findOrFail($closingId)->snapshot);
        $this->assertDatabaseCount('daily_finance_cash_handovers', 1);
        $this->assertDatabaseCount('daily_finance_day_closings', 1);
        $this->assertDatabaseCount('daily_finance_payment_settlements', 1);
        $this->assertSame(2, PaymentSettlementReceipt::count());
        $this->assertDatabaseCount('pos_cash_movements', 1);
    }
}
