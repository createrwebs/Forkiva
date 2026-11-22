<?php

namespace Modules\DailyFinance\Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Modules\Branch\Models\Branch;
use Modules\Core\Contracts\FinancialExpenseSummaryReaderInterface;
use Modules\Core\Services\Accounting\NullFinancialExpenseSummaryReader;
use Modules\Order\Enums\OrderPaymentStatus;
use Modules\Order\Enums\OrderStatus;
use Modules\Order\Enums\OrderType;
use Modules\Order\Models\Order;
use Modules\Payment\Enums\PaymentMethod;
use Modules\Payment\Enums\PaymentType;
use Modules\Payment\Models\Payment;
use Modules\Pos\Enums\PosCashDirection;
use Modules\Pos\Enums\PosCashReason;
use Modules\Pos\Models\PosCashMovement;
use Modules\Pos\Models\PosRegister;
use Modules\Pos\Models\PosSession;
use Modules\Setting\Repositories\SettingRepository;
use Modules\Support\Money;
use Modules\User\Models\Permission;
use Modules\User\Models\User;
use Tests\TestCase;

class DailyFinanceTest extends TestCase
{
    use RefreshDatabase;

    protected Branch $branch;

    protected User $user;

    protected function setUp(): void
    {
        parent::setUp();
        config(['app.timezone' => 'UTC']);
        $this->travelTo(now()->setDate(2026, 10, 11)->setTime(12, 0));
        $this->branch = Branch::factory()->create(['currency' => 'JOD', 'timezone' => 'Asia/Amman']);
        $this->user = User::factory()->create(['branch_id' => $this->branch->id, 'is_active' => true]);
        $this->actingAs($this->user);
        app()->instance(FinancialExpenseSummaryReaderInterface::class, new NullFinancialExpenseSummaryReader);
    }

    public function test_access_and_each_source_permission_are_enforced(): void
    {
        $this->getJson($this->url())->assertForbidden();
        $this->grant('admin.payments.index');
        $this->getJson($this->url())->assertForbidden();
        $this->grant('admin.daily_finance.index');
        $this->getJson($this->url())->assertOk()
            ->assertJsonPath('body.permissions.sales', false)
            ->assertJsonPath('body.sales', null)
            ->assertJsonPath('body.sessions', null)
            ->assertJsonPath('body.movements', null)
            ->assertJsonPath('body.expenses', null)
            ->assertJsonPath('body.payments.collections.amount', 0);
        $other = Branch::factory()->create();
        $this->getJson($this->url($other->id))->assertNotFound();
        $this->getJson('/api/v1/daily-finance?date=invalid')->assertUnprocessable();
    }

    public function test_receipts_use_branch_midnight_and_their_own_date_without_double_counting_refunds(): void
    {
        $this->grantAll();
        $order = $this->order(['created_at' => '2026-10-10 09:00:00']);
        $this->payment($order, '12.345', '2026-10-10 21:00:00');
        $this->payment($order, '2.345', '2026-10-11 08:00:00', PaymentType::Refund);
        $this->payment($order, '100', '2026-10-10 20:59:59');
        $this->payment($order, '200', '2026-10-11 21:00:00');
        $this->payment($order, '300', '2026-10-11 10:00:00')->update(['voided_at' => now()]);
        $this->payment($order, '400', '2026-10-11 10:00:00')->delete();
        $this->payment($order, '500', '2026-10-11 10:00:00')->update(['currency' => 'USD']);
        $legacy = $this->payment($order, '1.111', '2026-10-11 11:00:00');
        $legacy->forceFill(['received_at' => null, 'created_at' => '2026-10-11 11:00:00'])->save();
        $this->getJson($this->url())->assertOk()
            ->assertJsonPath('body.meta.timezone', 'Asia/Amman')
            ->assertJsonPath('body.sales.total.amount', 0)
            ->assertJsonPath('body.payments.collections.amount', 13.456)
            ->assertJsonPath('body.payments.refunds.amount', 2.345)
            ->assertJsonPath('body.payments.net.amount', 11.111)
            ->assertJsonPath('body.payments.excluded_currency_count', 1)
            ->assertJsonCount(6, 'body.payments.methods');
    }

    public function test_module_access_alone_does_not_grant_source_data(): void
    {
        $this->grant('admin.daily_finance.index');
        $response = $this->getJson($this->url())->assertOk();
        foreach (['sales', 'payments', 'movements', 'sessions', 'expenses'] as $section) {
            $response->assertJsonPath('body.permissions.'.$section, false)
                ->assertJsonPath('body.'.$section, null);
        }

        $this->grant('admin.payments.index');
        $this->getJson($this->url())->assertOk()->assertJsonPath('body.payments.collections.amount', 0);
    }

    public function test_the_removed_dashboard_endpoint_is_not_found(): void
    {
        $this->grantAll();
        $this->getJson('/api/v1/dashboards/daily-finance?date=2026-10-11')->assertNotFound();
    }

    public function test_session_cash_requires_detail_permission_even_when_the_session_list_is_accessible(): void
    {
        $this->grant('admin.daily_finance.index');
        $this->grant('admin.pos_sessions.index');
        $this->getJson($this->url())->assertOk()
            ->assertJsonPath('body.permissions.sessions', false)
            ->assertJsonPath('body.sessions', null);
        $this->grant('admin.pos_sessions.show');
        $this->getJson($this->url())->assertOk()
            ->assertJsonPath('body.permissions.sessions', true)
            ->assertJsonCount(0, 'body.sessions.rows');
    }

    public function test_sales_and_current_dues_exclude_cancelled_orders_and_other_branches(): void
    {
        $this->grantAll();
        $this->order(['total' => '20.123', 'due_amount' => '5.123', 'created_at' => '2026-10-10 21:00:00']);
        $this->order(['total' => '100', 'status' => OrderStatus::Cancelled]);
        $this->order(['total' => '100', 'status' => OrderStatus::Merged]);
        $this->order(['total' => '100', 'status' => OrderStatus::Refunded]);
        $this->order(['total' => '100', 'currency' => 'USD']);
        $other = Branch::factory()->create();
        $this->order(['total' => '500'])->forceFill(['branch_id' => $other->id])->saveQuietly();
        $this->getJson($this->url())->assertOk()
            ->assertJsonPath('body.sales.total.amount', 20.123)
            ->assertJsonPath('body.sales.dues.amount', 5.123)
            ->assertJsonPath('body.sales.orders_count', 1)
            ->assertJsonPath('body.sales.excluded_currency_count', 1);
    }

    public function test_successive_sessions_count_the_latest_drawer_once_and_open_counts_remain_unknown(): void
    {
        $this->grantAll();
        $register = PosRegister::factory()->create(['branch_id' => $this->branch->id]);
        $closed = $this->createSession($register, [
            'opened_at' => '2026-10-11 04:00:00', 'closed_at' => '2026-10-11 08:00:00',
            'status' => 'closed', 'declared_cash' => '100',
            'meta' => ['cash_reconciliation' => ['expected_cash' => '101']],
        ]);
        $open = $this->createSession($register, ['opened_at' => '2026-10-11 09:00:00', 'opening_float' => '100']);
        $this->movement($open, '20.100', PosCashDirection::In, PosCashReason::Sale);
        $this->movement($open, '5.050', PosCashDirection::Out, PosCashReason::PayOut);
        $this->movement($open, '999', PosCashDirection::Adjust, PosCashReason::ClosingAdjust);
        $this->getJson($this->url())->assertOk()
            ->assertJsonPath('body.sessions.expected.amount', 115.05)
            ->assertJsonPath('body.sessions.counted', null)
            ->assertJsonPath('body.sessions.variance', null)
            ->assertJsonPath('body.sessions.open_count', 1)
            ->assertJsonPath('body.sessions.closed_count', 1)
            ->assertJsonPath('body.sessions.rows.1.latest_for_register', false)
            ->assertJsonPath('body.sessions.rows.1.variance.amount', -1)
            ->assertJsonPath('body.movements.cash_in.amount', 20.1)
            ->assertJsonPath('body.movements.cash_out.amount', 5.05)
            ->assertJsonPath('body.movements.payouts.amount', 5.05);
        $this->assertSame('closed', $closed->fresh()->status->value);
        $this->assertDatabaseCount('pos_cash_movements', 3);
    }

    public function test_session_opening_uses_system_format_and_branch_timezone_without_changing_iso_timestamp(): void
    {
        $this->grantAll();
        $register = PosRegister::factory()->create(['branch_id' => $this->branch->id]);
        $this->createSession($register, ['opened_at' => '2026-10-10 22:15:00']);
        $settings = collect([
            'default_date_format' => 'd/m/Y',
            'default_time_format' => 'H:i',
        ])->map(fn (string $value) => [
            'payload' => serialize($value),
            'is_encryptable' => false,
            'is_translatable' => false,
        ]);
        app()->instance('setting', new SettingRepository($settings));

        $this->getJson($this->url())->assertOk()
            ->assertJsonPath('body.sessions.rows.0.opened_at', '2026-10-10T22:15:00+00:00')
            ->assertJsonPath('body.sessions.rows.0.opened_at_formatted', '11/10/2026 01:15');
    }

    public function test_overnight_sessions_use_only_movements_before_day_end_and_do_not_expose_future_count(): void
    {
        $this->grantAll();
        $register = PosRegister::factory()->create(['branch_id' => $this->branch->id]);
        $session = $this->createSession($register, [
            'opened_at' => '2026-10-10 20:00:00', 'closed_at' => '2026-10-12 02:00:00',
            'status' => 'closed', 'opening_float' => '100', 'declared_cash' => '999',
            'meta' => ['cash_reconciliation' => ['expected_cash' => '999']],
        ]);
        $this->movement($session, '20', PosCashDirection::In, PosCashReason::PayIn, '2026-10-10 20:30:00');
        $this->movement($session, '10', PosCashDirection::Out, PosCashReason::PayOut);
        $this->movement($session, '889', PosCashDirection::In, PosCashReason::PayIn, '2026-10-11 21:00:00');
        $this->getJson($this->url())->assertOk()
            ->assertJsonPath('body.sessions.expected.amount', 110)
            ->assertJsonPath('body.sessions.rows.0.status', 'open')
            ->assertJsonPath('body.sessions.rows.0.closed_at', null)
            ->assertJsonPath('body.sessions.counted', null)
            ->assertJsonPath('body.movements.cash_in.amount', 0);
    }

    public function test_closed_snapshots_are_not_recomputed_and_missing_legacy_evidence_is_not_invented(): void
    {
        $this->grantAll();
        $register = PosRegister::factory()->create(['branch_id' => $this->branch->id]);
        $session = $this->createSession($register, [
            'closed_at' => '2026-10-11 10:00:00', 'status' => 'closed', 'declared_cash' => '98',
            'meta' => ['cash_reconciliation' => ['expected_cash' => '100']],
        ]);
        $this->movement($session, '500', PosCashDirection::In, PosCashReason::Sale);
        $this->getJson($this->url())->assertOk()
            ->assertJsonPath('body.sessions.expected.amount', 100)
            ->assertJsonPath('body.sessions.counted.amount', 98)
            ->assertJsonPath('body.sessions.variance.amount', -2);
        $session->update(['meta' => null]);
        $this->getJson($this->url())->assertOk()
            ->assertJsonPath('body.sessions.expected', null)
            ->assertJsonPath('body.sessions.variance', null);
    }

    public function test_optional_expenses_preserve_ledger_currency_and_are_never_queried_without_permission(): void
    {
        $this->grant('admin.payments.index');
        $this->grant('admin.daily_finance.index');
        $reader = Mockery::mock(FinancialExpenseSummaryReaderInterface::class);
        $reader->shouldReceive('forDate')->once()->with($this->branch->id, '2026-10-11')->andReturn(new Money(12.5, 'USD'));
        app()->instance(FinancialExpenseSummaryReaderInterface::class, $reader);
        $this->getJson($this->url())->assertOk()->assertJsonPath('body.expenses', null);
        $this->grant('admin.accounting_reports.index');
        $this->getJson($this->url())->assertOk()
            ->assertJsonPath('body.expenses.amount', 12.5)
            ->assertJsonPath('body.expenses.currency', 'USD');
    }

    protected function grant(string $permission): void
    {
        $this->user->givePermissionTo(Permission::findOrCreate($permission, 'api'));
    }

    protected function grantAll(): void
    {
        foreach ([
            'admin.daily_finance.index',
            'admin.orders.index',
            'admin.payments.index',
            'admin.pos_sessions.index',
            'admin.pos_sessions.show',
            'admin.pos_cash_movements.index',
            'admin.accounting_reports.index',
        ] as $permission) {
            $this->grant($permission);
        }
    }

    public function test_entries_require_module_source_permission_and_authorized_branch(): void
    {
        $url = $this->entriesUrl('collections');
        $this->getJson($url)->assertForbidden();
        $this->grant('admin.daily_finance.index');
        $this->getJson($url)->assertForbidden();
        $this->grant('admin.payments.index');
        $this->getJson($url)->assertOk()->assertJsonCount(0, 'body.data');
        $other = Branch::factory()->create();
        $this->getJson($this->entriesUrl('collections', ['branch_id' => $other->id]))->assertNotFound();
        $this->getJson($this->entriesUrl('sales'))->assertForbidden();
        $this->getJson($this->entriesUrl('movements'))->assertForbidden();
        $this->getJson($this->entriesUrl('unknown'))->assertUnprocessable();
        $this->getJson($this->entriesUrl('collections', ['date' => 'invalid']))->assertUnprocessable();
        $this->getJson($this->entriesUrl('collections', ['page' => 0]))->assertUnprocessable();
    }

    public function test_receipt_entries_match_branch_day_currency_and_refund_semantics(): void
    {
        $this->grantAll();
        $order = $this->order(['created_at' => '2026-10-09 08:00:00']);
        $included = $this->payment($order, '12.345', '2026-10-10 21:00:00');
        $included->update(['cashier_id' => $this->user->id]);
        $refund = $this->payment($order, '2.345', '2026-10-11 08:00:00', PaymentType::Refund);
        $this->payment($order, '100', '2026-10-10 20:59:59');
        $this->payment($order, '100', '2026-10-11 21:00:00');
        $this->payment($order, '100', '2026-10-11 10:00:00')->update(['currency' => 'USD']);
        $this->payment($order, '100', '2026-10-11 10:00:00')->update(['voided_at' => now()]);
        $this->payment($order, '100', '2026-10-11 10:00:00')->delete();
        $legacy = $this->payment($order, '1.111', '2026-10-11 11:00:00');
        $legacy->forceFill(['received_at' => null, 'created_at' => '2026-10-11 11:00:00'])->save();
        $this->getJson($this->entriesUrl('collections'))->assertOk()
            ->assertJsonPath('body.pagination.total', 2)
            ->assertJsonPath('body.data.0.id', $legacy->id)
            ->assertJsonPath('body.data.1.id', $included->id)
            ->assertJsonPath('body.data.1.amount.amount', 12.345)
            ->assertJsonPath('body.data.1.label.id', 'cash')
            ->assertJsonPath('body.data.1.receipt.cashier', $this->user->name)
            ->assertJsonPath('body.data.0.receipt.cashier', null)
            ->assertJsonPath('body.data.1.occurred_at_formatted', dateTimeFormat($included->received_at, timezone: 'Asia/Amman'))
            ->assertJsonPath('body.data.0.occurred_at_formatted', dateTimeFormat($legacy->created_at, timezone: 'Asia/Amman'));
        $this->getJson($this->entriesUrl('refunds'))->assertOk()
            ->assertJsonCount(1, 'body.data')
            ->assertJsonPath('body.data.0.id', $refund->id)
            ->assertJsonPath('body.data.0.amount.amount', 2.345);
    }

    public function test_order_entries_exclude_cancelled_orders_and_show_only_current_dues_when_requested(): void
    {
        $this->grantAll();
        $due = $this->order(['total' => '20.123', 'due_amount' => '5.123']);
        $this->order(['total' => '10', 'due_amount' => '0']);
        $this->order(['total' => '100', 'status' => OrderStatus::Cancelled]);
        $this->order(['total' => '100', 'status' => OrderStatus::Merged]);
        $this->order(['total' => '100', 'status' => OrderStatus::Refunded]);
        $this->order(['total' => '100', 'currency' => 'USD']);
        $this->order(['created_at' => '2026-10-10 20:59:59']);
        $this->getJson($this->entriesUrl('sales'))->assertOk()->assertJsonCount(2, 'body.data');
        $this->getJson($this->entriesUrl('dues'))->assertOk()->assertJsonCount(1, 'body.data')
            ->assertJsonPath('body.data.0.id', $due->id)
            ->assertJsonPath('body.data.0.amount.amount', 5.123);
    }

    public function test_sales_breakdown_preserves_money_and_formats_time_in_the_branch_timezone(): void
    {
        $this->grantAll();
        $order = $this->order(['subtotal' => '20.123', 'total' => '22.723', 'due_amount' => '2.345']);
        $order->allTaxes()->create([
            'name' => ['en' => 'Tax'], 'rate' => 10, 'currency' => 'JOD',
            'amount' => '2.100', 'type' => 'exclusive',
        ]);
        $order->charges()->create([
            'code' => 'service', 'name' => ['en' => 'Service'], 'currency' => 'JOD',
            'unit_amount' => '0.500', 'quantity' => 1, 'amount' => '0.500',
        ]);
        $response = $this->getJson($this->entriesUrl('sales'))->assertOk();
        $response->assertJsonPath('body.data.0.order.subtotal.amount', 20.123)
            ->assertJsonPath('body.data.0.order.tax.amount', 2.1)
            ->assertJsonPath('body.data.0.order.charges.amount', 0.5)
            ->assertJsonPath('body.data.0.order.discount.amount', 0)
            ->assertJsonPath('body.data.0.order.total.amount', 22.723)
            ->assertJsonPath('body.data.0.order.due.amount', 2.345)
            ->assertJsonPath('body.data.0.order.type.id', 'takeaway')
            ->assertJsonPath('body.data.0.occurred_at_formatted', dateTimeFormat($order->created_at, timezone: 'Asia/Amman'));
        $this->getJson($this->entriesUrl('dues'))->assertOk()
            ->assertJsonPath('body.data.0.amount.amount', 2.345)
            ->assertJsonPath('body.data.0.order.total.amount', 22.723);
    }

    public function test_movement_entries_are_paginated_and_exclude_non_physical_adjustments(): void
    {
        $this->grantAll();
        $register = PosRegister::factory()->create(['branch_id' => $this->branch->id]);
        $session = $this->createSession($register);
        for ($index = 0; $index < 16; $index++) {
            $this->movement($session, '1.234', PosCashDirection::In, PosCashReason::PayIn);
        }
        $this->movement($session, '50', PosCashDirection::Adjust, PosCashReason::ClosingAdjust);
        $this->movement($session, '50', PosCashDirection::Out, PosCashReason::PayOut, '2026-10-10 20:59:59');
        $this->getJson($this->entriesUrl('movements'))->assertOk()->assertJsonCount(15, 'body.data')
            ->assertJsonPath('body.pagination.total', 16)
            ->assertJsonPath('body.data.0.direction.id', 'in');
        $this->getJson($this->entriesUrl('movements', ['page' => 2]))->assertOk()->assertJsonCount(1, 'body.data');
    }

    public function test_movement_details_preserve_historical_register_balances_and_branch_time(): void
    {
        $this->grant('admin.daily_finance.index');
        $this->grant('admin.pos_cash_movements.index');
        $register = PosRegister::factory()->create(['branch_id' => $this->branch->id, 'name' => 'Front counter']);
        $session = $this->createSession($register);
        $movement = PosCashMovement::query()->create([
            'branch_id' => $this->branch->id,
            'pos_register_id' => $register->id,
            'pos_session_id' => $session->id,
            'direction' => PosCashDirection::Out,
            'reason' => PosCashReason::PayOut,
            'amount' => '12.345',
            'currency' => 'JOD',
            'currency_rate' => 1,
            'balance_before' => '10.000',
            'balance_after' => '-2.345',
            'reference' => 'PETTY-001',
            'occurred_at' => '2026-10-10 21:00:00',
        ]);
        $register->delete();

        $this->getJson($this->entriesUrl('movements'))->assertOk()
            ->assertJsonPath('body.data.0.id', $movement->id)
            ->assertJsonPath('body.data.0.reference', 'PETTY-001')
            ->assertJsonPath('body.data.0.direction.id', 'out')
            ->assertJsonPath('body.data.0.amount.amount', 12.345)
            ->assertJsonPath('body.data.0.amount.precision', 3)
            ->assertJsonPath('body.data.0.movement.register', 'Front counter')
            ->assertJsonPath('body.data.0.movement.balance_before.amount', 10)
            ->assertJsonPath('body.data.0.movement.balance_after.amount', -2.345)
            ->assertJsonPath('body.data.0.movement.balance_after.currency', 'JOD')
            ->assertJsonPath('body.data.0.occurred_at_formatted', dateTimeFormat($movement->occurred_at, timezone: 'Asia/Amman'));
    }

    protected function entriesUrl(string $kind, array $filters = []): string
    {
        return '/api/v1/daily-finance/entries?'.http_build_query([
            'branch_id' => $this->branch->id,
            'date' => '2026-10-11',
            'kind' => $kind,
            ...$filters,
        ]);
    }

    protected function url(?int $branchId = null): string
    {
        return '/api/v1/daily-finance?'.http_build_query(['branch_id' => $branchId ?? $this->branch->id, 'date' => '2026-10-11']);
    }

    protected function order(array $attributes = []): Order
    {
        return Order::query()->create([
            'branch_id' => $this->branch->id, 'status' => OrderStatus::Completed, 'type' => OrderType::Takeaway,
            'payment_status' => OrderPaymentStatus::Paid, 'currency' => 'JOD', 'currency_rate' => 1,
            'subtotal' => '10', 'total' => '10', 'due_amount' => '0', 'order_date' => '2026-10-11',
            'created_at' => '2026-10-11 08:00:00', ...$attributes,
        ]);
    }

    protected function payment(Order $order, string $amount, string $receivedAt, PaymentType $type = PaymentType::Payment): Payment
    {
        return $order->payments()->create([
            'branch_id' => $order->branch_id, 'order_reference_no' => $order->reference_no,
            'method' => PaymentMethod::Cash, 'type' => $type, 'amount' => $amount,
            'currency' => 'JOD', 'currency_rate' => 1, 'received_at' => $receivedAt,
        ]);
    }

    protected function createSession(PosRegister $register, array $attributes = []): PosSession
    {
        return PosSession::factory()->create([
            'branch_id' => $this->branch->id, 'pos_register_id' => $register->id, 'opened_by' => $this->user->id,
            'opened_at' => '2026-10-11 04:00:00', 'opening_float' => '0', ...$attributes,
        ]);
    }

    protected function movement(PosSession $session, string $amount, PosCashDirection $direction, PosCashReason $reason, string $occurredAt = '2026-10-11 09:00:00'): void
    {
        PosCashMovement::query()->create([
            'branch_id' => $session->branch_id, 'pos_register_id' => $session->pos_register_id,
            'pos_session_id' => $session->id, 'direction' => $direction, 'reason' => $reason,
            'amount' => $amount, 'currency' => 'JOD', 'currency_rate' => 1, 'occurred_at' => $occurredAt,
        ]);
    }
}
