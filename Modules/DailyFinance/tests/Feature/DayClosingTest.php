<?php

namespace Modules\DailyFinance\Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use LogicException;
use Modules\Branch\Models\Branch;
use Modules\Core\Contracts\FinancialExpenseSummaryReaderInterface;
use Modules\Core\Services\Accounting\NullFinancialExpenseSummaryReader;
use Modules\DailyFinance\Enums\CashHandoverStatus;
use Modules\DailyFinance\Models\CashHandover;
use Modules\DailyFinance\Models\DayClosing;
use Modules\Order\Enums\OrderPaymentStatus;
use Modules\Order\Enums\OrderStatus;
use Modules\Order\Enums\OrderType;
use Modules\Order\Models\Order;
use Modules\Pos\Enums\PosSessionStatus;
use Modules\Pos\Models\PosRegister;
use Modules\Pos\Models\PosSession;
use Modules\Support\Money;
use Modules\User\Models\Permission;
use Modules\User\Models\User;
use Tests\TestCase;

class DayClosingTest extends TestCase
{
    use RefreshDatabase;

    protected Branch $branch;

    protected User $user;

    protected PosSession $session;

    protected const PERMISSIONS = [
        'admin.daily_finance.index',
        'admin.daily_finance_closings.index',
        'admin.daily_finance_closings.close',
        'admin.orders.index',
        'admin.payments.index',
        'admin.pos_cash_movements.index',
        'admin.pos_sessions.index',
        'admin.pos_sessions.show',
        'admin.daily_finance_handovers.index',
    ];

    protected function setUp(): void
    {
        parent::setUp();
        config(['app.timezone' => 'UTC']);
        $this->travelTo(now()->setDate(2026, 10, 12)->setTime(12, 0));
        $this->branch = Branch::factory()->create(['currency' => 'JOD', 'timezone' => 'Asia/Amman']);
        $this->user = User::factory()->create(['branch_id' => $this->branch->id, 'is_active' => true]);
        foreach (self::PERMISSIONS as $permission) {
            $this->user->givePermissionTo(Permission::findOrCreate($permission, 'api'));
        }
        $this->actingAs($this->user);
        app()->instance(FinancialExpenseSummaryReaderInterface::class, new NullFinancialExpenseSummaryReader);
        $register = PosRegister::factory()->create(['branch_id' => $this->branch->id]);
        $this->session = PosSession::factory()->create([
            'branch_id' => $this->branch->id,
            'pos_register_id' => $register->id,
            'opened_by' => $this->user->id,
            'opened_at' => '2026-10-12 06:00:00',
            'closed_at' => '2026-10-12 08:00:00',
            'status' => PosSessionStatus::Closed,
            'opening_float' => '0',
            'declared_cash' => '10.345',
            'meta' => ['cash_reconciliation' => ['expected_cash' => '10.345'], 'payment_reconciliation' => ['currency' => 'JOD']],
        ]);
    }

    protected function filters(array $extra = []): array
    {
        return ['branch_id' => $this->branch->id, 'date' => '2026-10-12', ...$extra];
    }

    protected function review(array $extra = []): array
    {
        return $this->getJson('/api/v1/daily-finance/closings/review?'.http_build_query($this->filters($extra)))->assertOk()->json('body');
    }

    protected function payload(array $extra = []): array
    {
        return [...$this->filters(), 'review_hash' => $this->review()['review_hash'], 'request_key' => (string) Str::uuid(), 'notes' => null, ...$extra];
    }

    public function test_closing_is_immutable_exact_and_idempotent_without_changing_sources(): void
    {
        $order = $this->order();
        $before = $this->session->fresh()->getAttributes();
        $payload = $this->payload();
        $response = $this->postJson('/api/v1/daily-finance/closings', $payload)->assertOk()
            ->assertJsonPath('body.reference', 'DC-0001')
            ->assertJsonPath('body.summary.sales.total.amount', 10.345)
            ->assertJsonPath('body.summary.expenses', null)
            ->assertJsonPath('body.created_by.id', $this->user->id);
        $this->postJson('/api/v1/daily-finance/closings', [...$payload, 'request_key' => strtoupper($payload['request_key'])])->assertOk()->assertJsonPath('body.id', $response->json('body.id'));
        $this->postJson('/api/v1/daily-finance/closings', [...$payload, 'request_key' => (string) Str::uuid()])->assertConflict();
        $row = DayClosing::firstOrFail();
        $this->assertSame($row->reference_no, $response->json('body.reference'));
        $this->assertSame('10.345', $row->snapshot['summary']['sales']['total']);
        $this->assertSame($before, $this->session->fresh()->getAttributes());
        $this->assertDatabaseCount('daily_finance_day_closings', 1);
        $this->assertDatabaseCount('pos_cash_movements', 0);
        $order->update(['total' => '20.345']);
        $this->assertSame(10.345, $this->review()['summary']['sales']['total']['amount']);
        $this->getJson('/api/v1/daily-finance?'.http_build_query($this->filters()))->assertOk()->assertJsonPath('body.sales.total.amount', 20.345);
        $this->expectException(LogicException::class);
        $row->update(['notes' => 'Rewrite history']);
    }

    public function test_changed_review_is_rejected_and_requires_a_new_review(): void
    {
        $payload = $this->payload();
        $this->order();
        $this->postJson('/api/v1/daily-finance/closings', $payload)->assertConflict();
        $this->assertDatabaseCount('daily_finance_day_closings', 0);
        $payload['review_hash'] = $this->review()['review_hash'];
        $this->postJson('/api/v1/daily-finance/closings', $payload)->assertOk();
    }

    public function test_open_sessions_missing_counts_pending_receipts_and_future_days_block_closing(): void
    {
        $this->session->update(['status' => PosSessionStatus::Open, 'closed_at' => null]);
        $this->assertContains('open_sessions', $this->review()['blockers']);
        $this->postJson('/api/v1/daily-finance/closings', $this->payload())->assertUnprocessable()->assertJsonValidationErrors('date');
        $this->session->update(['status' => PosSessionStatus::Closed, 'closed_at' => '2026-10-12 08:00:00', 'declared_cash' => null]);
        $this->assertContains('unknown_counts', $this->review()['blockers']);
        $this->session->update(['declared_cash' => '10.345']);
        $receiver = User::factory()->create(['branch_id' => $this->branch->id]);
        $handover = CashHandover::create([
            'branch_id' => $this->branch->id, 'pos_session_id' => $this->session->id,
            'cashier_id' => $this->user->id, 'receiver_id' => $receiver->id,
            'request_key' => (string) Str::uuid(), 'currency' => 'JOD',
            'counted_cash' => '10.345', 'amount' => '1', 'status' => CashHandoverStatus::Pending,
        ]);
        $this->assertContains('pending_handovers', $this->review()['blockers']);
        $this->postJson('/api/v1/daily-finance/closings', $this->payload())->assertUnprocessable();
        $handover->update(['status' => CashHandoverStatus::Cancelled]);
        $this->assertSame([], $this->review()['blockers']);
        $this->assertContains('future_day', $this->review(['date' => '2026-10-13'])['blockers']);
    }

    public function test_session_and_order_differences_require_explanation(): void
    {
        $this->session->update(['declared_cash' => '10.000']);
        $this->order(['due_amount' => '1']);
        $review = $this->review();
        $this->assertContains('session_variances', $review['warnings']);
        $this->assertContains('unpaid_orders', $review['warnings']);
        $payload = $this->payload();
        $this->postJson('/api/v1/daily-finance/closings', $payload)->assertUnprocessable()->assertJsonValidationErrors('notes');
        $this->postJson('/api/v1/daily-finance/closings', [...$payload, 'notes' => 'Counted shortage and customer dues reviewed'])->assertOk();
    }

    public function test_every_source_permission_and_close_permission_are_enforced(): void
    {
        foreach (self::PERMISSIONS as $permission) {
            $payload = $this->payload();
            $this->user->revokePermissionTo($permission);
            $this->postJson('/api/v1/daily-finance/closings', $payload)->assertForbidden();
            if ($permission !== 'admin.daily_finance_closings.close') {
                $this->getJson('/api/v1/daily-finance/closings/review?'.http_build_query($this->filters()))->assertForbidden();
                $this->getJson('/api/v1/daily-finance/closings?branch_id='.$this->branch->id)->assertForbidden();
            } else {
                $this->assertFalse($this->review()['can_close']);
            }
            $this->user->givePermissionTo($permission);
        }
    }

    public function test_history_is_branch_scoped_and_paginated_and_validates_inputs(): void
    {
        $this->postJson('/api/v1/daily-finance/closings', $this->payload())->assertOk();
        $this->getJson('/api/v1/daily-finance/closings?branch_id='.$this->branch->id.'&per_page=1')->assertOk()
            ->assertJsonCount(1, 'body.data')->assertJsonPath('body.pagination.total', 1);
        $other = Branch::factory()->create();
        $this->getJson('/api/v1/daily-finance/closings?branch_id='.$other->id)->assertNotFound();
        $this->getJson('/api/v1/daily-finance/closings/review?branch_id='.$other->id.'&date=2026-10-12')->assertNotFound();
        $this->postJson('/api/v1/daily-finance/closings', $this->payload(['branch_id' => $other->id]))->assertNotFound();
        $this->postJson('/api/v1/daily-finance/closings', $this->payload(['request_key' => 'invalid']))->assertUnprocessable();
        $this->getJson('/api/v1/daily-finance/closings/review?branch_id='.$this->branch->id.'&date=invalid')->assertUnprocessable();
    }

    public function test_historical_expenses_are_hidden_when_viewer_loses_accounting_access(): void
    {
        $this->user->givePermissionTo(Permission::findOrCreate('admin.accounting_reports.index', 'api'));
        app()->instance(FinancialExpenseSummaryReaderInterface::class, new class implements FinancialExpenseSummaryReaderInterface
        {
            public function forDate(int $branchId, string $date): ?Money
            {
                return new Money(2.345, 'JOD');
            }
        });
        $this->postJson('/api/v1/daily-finance/closings', $this->payload())->assertOk()->assertJsonPath('body.summary.expenses.amount', 2.345);
        $this->user->revokePermissionTo('admin.accounting_reports.index');
        $this->assertNull($this->review()['summary']['expenses']);
        $this->getJson('/api/v1/daily-finance/closings?branch_id='.$this->branch->id)->assertOk()->assertJsonPath('body.data.0.summary.expenses', null);
    }

    public function test_snapshot_does_not_expose_other_branches_from_a_global_review(): void
    {
        $other = Branch::factory()->create();
        $this->user->update(['branch_id' => null]);
        $this->postJson('/api/v1/daily-finance/closings', $this->payload())->assertOk()
            ->assertJsonCount(1, 'body.summary.branches')
            ->assertJsonPath('body.summary.branches.0.id', $this->branch->id);
        $this->assertNotContains($other->id, array_column(DayClosing::firstOrFail()->snapshot['summary']['branches'], 'id'));
    }

    protected function order(array $attributes = []): Order
    {
        return Order::create([
            'branch_id' => $this->branch->id, 'status' => OrderStatus::Completed, 'type' => OrderType::Takeaway,
            'payment_status' => OrderPaymentStatus::Paid, 'currency' => 'JOD', 'currency_rate' => 1,
            'subtotal' => '10.345', 'total' => '10.345', 'due_amount' => '0', 'order_date' => '2026-10-12',
            'created_at' => '2026-10-12 08:00:00', ...$attributes,
        ]);
    }
}
