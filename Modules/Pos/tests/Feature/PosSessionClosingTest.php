<?php

namespace Modules\Pos\Tests\Feature;

use Brick\Math\BigDecimal;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Validation\ValidationException;
use Modules\Branch\Models\Branch;
use Modules\Currency\Currency;
use Modules\Order\Enums\OrderPaymentStatus;
use Modules\Order\Enums\OrderStatus;
use Modules\Order\Enums\OrderType;
use Modules\Order\Models\Order;
use Modules\Payment\Enums\PaymentMethod;
use Modules\Payment\Enums\PaymentType;
use Modules\Payment\Models\Payment;
use Modules\Pos\Enums\PosCashDirection;
use Modules\Pos\Enums\PosCashReason;
use Modules\Pos\Enums\PosSessionStatus;
use Modules\Pos\Events\ClosePosSession;
use Modules\Pos\Models\PosCashMovement;
use Modules\Pos\Models\PosRegister;
use Modules\Pos\Models\PosSession;
use Modules\Pos\Services\PosCashMovement\PosCashMovementServiceInterface;
use Modules\Pos\Services\PosSession\PosSessionServiceInterface;
use Modules\Pos\Transformers\Api\V1\PosSessionShowResource;
use Modules\User\Models\User;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class PosSessionClosingTest extends TestCase
{
    use RefreshDatabase;

    public function test_closing_counts_opening_float_and_physical_movements_once(): void
    {
        $session = $this->createSession('USD', '500');
        $order = $this->createOrder($session, '1000');
        $payment = $this->createPayment($order, PaymentMethod::Cash, '1000');
        $this->createMovement($session, PosCashDirection::In, PosCashReason::Sale, '1000', $payment);
        $this->createMovement($session, PosCashDirection::Out, PosCashReason::PayOut, '200');
        $this->createMovement($session, PosCashDirection::Adjust, PosCashReason::Correction, '999');
        $this->createMovement($session, PosCashDirection::In, PosCashReason::ClosingAdjust, '999');
        $deleted = $this->createMovement($session, PosCashDirection::In, PosCashReason::PayIn, '999');
        $deleted->delete();
        $session->update(['meta' => ['existing_evidence' => 'preserved']]);

        $closed = $this->closeSession($session, '1300');

        $this->assertSame(PosSessionStatus::Closed, $closed->status);
        $this->assertEquals(1000, $closed->system_cash_sales->amount());
        $this->assertEquals(0, $closed->cash_over_short->amount());
        $this->assertSame('1300.00', $closed->meta['cash_reconciliation']['expected_cash']);
        $this->assertSame('1000.00', $closed->meta['cash_reconciliation']['cash_in']);
        $this->assertSame('200.00', $closed->meta['cash_reconciliation']['cash_out']);
        $this->assertSame('preserved', $closed->meta['existing_evidence']);
        Event::assertDispatched(ClosePosSession::class, fn ($event) => $event->session->id === $closed->id);
    }

    public function test_partial_refunds_do_not_increase_collections_or_depend_on_order_status(): void
    {
        $session = $this->createSession('USD', '500');
        $order = $this->createOrder($session, '1000');
        $receipt = $this->createPayment($order, PaymentMethod::Cash, '1000');
        $refund = $this->createPayment($order, PaymentMethod::Cash, '100', PaymentType::Refund);
        $this->createMovement($session, PosCashDirection::In, PosCashReason::Sale, '1000', $receipt);
        $this->createMovement($session, PosCashDirection::Out, PosCashReason::Refund, '100', $refund);
        $this->createPayment($order, PaymentMethod::Card, '200');
        $this->createPayment($order, PaymentMethod::Card, '20', PaymentType::Refund);
        $this->createPayment($order, PaymentMethod::MobileWallet, '30');
        $this->createPayment($order, PaymentMethod::MobileWallet, '5', PaymentType::Refund);
        $voided = $this->createPayment($order, PaymentMethod::Card, '777');
        $voided->update(['voided_at' => now()]);
        $voidedRefund = $this->createPayment($order, PaymentMethod::Card, '888', PaymentType::Refund);
        $voidedRefund->update(['voided_at' => now()]);
        $deleted = $this->createPayment($order, PaymentMethod::Card, '999');
        $deleted->delete();

        $closed = $this->closeSession($session, '1400');

        $this->assertSame(OrderStatus::Completed, $order->fresh()->status);
        $this->assertEquals(1000, $closed->system_cash_sales->amount());
        $this->assertEquals(200, $closed->system_card_sales->amount());
        $this->assertEquals(30, $closed->system_other_sales->amount());
        $this->assertEquals(1230, $closed->total_sales->amount());
        $this->assertEquals(125, $closed->total_refunds->amount());
        $this->assertEquals(0, $closed->cash_over_short->amount());
    }

    public function test_refunded_order_uses_actual_refund_payments_and_excludes_deleted_refunds(): void
    {
        $session = $this->createSession('USD', '0');
        $order = $this->createOrder($session, '1000');
        $order->update(['status' => OrderStatus::Refunded]);
        $this->createPayment($order, PaymentMethod::Card, '1000');
        $this->createPayment($order, PaymentMethod::Card, '100', PaymentType::Refund);
        $this->createPayment($order, PaymentMethod::Card, '900', PaymentType::Refund)->delete();

        $closed = $this->closeSession($session, '0');

        $this->assertEquals(1000, $closed->system_card_sales->amount());
        $this->assertEquals(100, $closed->total_refunds->amount());
    }

    public function test_voiding_a_payment_does_not_erase_cash_without_a_physical_reversal(): void
    {
        $session = $this->createSession('USD', '500');
        $order = $this->createOrder($session, '200');
        $payment = $this->createPayment($order, PaymentMethod::Cash, '200');
        $this->createMovement($session, PosCashDirection::In, PosCashReason::Sale, '200', $payment);
        $payment->update(['voided_at' => now()]);

        $closed = $this->closeSession($session, '700');

        $this->assertEquals(0, $closed->system_cash_sales->amount());
        $this->assertSame('700.00', $closed->meta['cash_reconciliation']['expected_cash']);
        $this->assertEquals(0, $closed->cash_over_short->amount());
    }

    public function test_topups_safe_drops_tips_and_corrections_affect_drawer_custody(): void
    {
        $session = $this->createSession('USD', '500');
        $this->createMovement($session, PosCashDirection::In, PosCashReason::Sale, '1000');
        $this->createMovement($session, PosCashDirection::In, PosCashReason::PayIn, '100');
        $this->createMovement($session, PosCashDirection::Out, PosCashReason::CashDrop, '300');
        $this->createMovement($session, PosCashDirection::In, PosCashReason::TipIn, '20');
        $this->createMovement($session, PosCashDirection::Out, PosCashReason::TipOut, '10');
        $this->createMovement($session, PosCashDirection::Out, PosCashReason::Correction, '10');

        $closed = $this->closeSession($session, '1300');

        $this->assertEquals(0, $closed->cash_over_short->amount());
        $this->assertSame('1120.00', $closed->meta['cash_reconciliation']['cash_in']);
        $this->assertSame('320.00', $closed->meta['cash_reconciliation']['cash_out']);
    }

    public function test_cash_collected_for_an_older_order_belongs_to_the_receiving_drawer(): void
    {
        $old = $this->createSession('USD', '0');
        $order = $this->createOrder($old, '200');
        $old->update(['status' => PosSessionStatus::Closed, 'cash_over_short' => 0]);
        $current = PosSession::factory()->create([
            'branch_id' => $old->branch_id,
            'pos_register_id' => $old->pos_register_id,
            'opened_by' => $old->opened_by,
            'opened_at' => now(),
            'opening_float' => '500',
        ])->load('branch');
        $payment = $this->createPayment($order, PaymentMethod::Cash, '200');
        $payment->update(['pos_session_id' => null]);
        $this->createMovement($current, PosCashDirection::In, PosCashReason::Sale, '200', $payment);

        $closed = $this->closeSession($current, '700');

        $this->assertEquals(0, $closed->cash_over_short->amount());
        $this->assertSame('700.00', $closed->meta['cash_reconciliation']['expected_cash']);
        $this->assertEquals(0, $old->fresh()->cash_over_short->amount());
    }

    public function test_opening_float_is_expected_even_when_there_are_no_sales(): void
    {
        $session = $this->createSession('USD', '500');

        $closed = $this->closeSession($session, '500');

        $this->assertEquals(0, $closed->total_sales->amount());
        $this->assertEquals(0, $closed->cash_over_short->amount());
        $this->assertSame('500.00', $closed->meta['cash_reconciliation']['expected_cash']);
    }

    public function test_currency_precision_and_threshold_are_compared_exactly(): void
    {
        $session = $this->createSession('JOD', '0.100');
        $session->branch->update(['cash_difference_threshold' => '1']);
        $this->createMovement($session, PosCashDirection::In, PosCashReason::PayIn, '0.200');
        $this->createMovement($session, PosCashDirection::Out, PosCashReason::PayOut, '0.100');

        $closed = $this->closeSession($session, '1.200');

        $this->assertSame('0.200', $closed->meta['cash_reconciliation']['expected_cash']);
        $this->assertEquals(1, $closed->cash_over_short->amount());
        $this->assertDatabaseHas('pos_cash_movements', [
            'pos_session_id' => $session->id,
            'reason' => PosCashReason::ClosingAdjust->value,
            'direction' => PosCashDirection::Adjust->value,
            'amount' => '1',
        ]);
    }

    public function test_three_subunit_currency_rejects_a_single_subunit_variance_at_zero_threshold(): void
    {
        $session = $this->createSession('JOD', '0.100');
        $this->createMovement($session, PosCashDirection::In, PosCashReason::PayIn, '0.200');
        $this->createMovement($session, PosCashDirection::Out, PosCashReason::PayOut, '0.100');

        try {
            $this->closeSession($session, '0.201');
            $this->fail('A single currency subunit exceeds a zero variance threshold.');
        } catch (ValidationException $exception) {
            $this->assertStringContainsString('0.001', $exception->errors()['declared_cash'][0]);
        }

        $this->assertSame(PosSessionStatus::Open, $session->fresh()->status);
        Event::assertNotDispatched(ClosePosSession::class);
    }

    public function test_shortage_is_saved_with_its_sign_and_adjustment_does_not_hide_it(): void
    {
        $session = $this->createSession('USD', '500');
        $session->branch->update(['cash_difference_threshold' => '20']);
        $this->createMovement($session, PosCashDirection::In, PosCashReason::Sale, '1000');
        $this->createMovement($session, PosCashDirection::Out, PosCashReason::PayOut, '200');

        $closed = $this->closeSession($session, '1280');

        $this->assertEquals(-20, $closed->cash_over_short->amount());
        $this->assertSame('1300.00', $closed->meta['cash_reconciliation']['expected_cash']);
        $this->assertSame(1, PosCashMovement::query()->where('pos_session_id', $closed->id)->where('reason', PosCashReason::ClosingAdjust)->count());
    }

    public function test_surplus_is_saved_with_a_positive_sign_in_a_zero_subunit_currency(): void
    {
        $session = $this->createSession('JPY', '500');
        $session->branch->update(['cash_difference_threshold' => '20']);

        $closed = $this->closeSession($session, '520');

        $this->assertEquals(20, $closed->cash_over_short->amount());
        $this->assertSame('500', $closed->meta['cash_reconciliation']['expected_cash']);
    }

    public function test_movements_from_another_branch_do_not_affect_expected_cash(): void
    {
        $session = $this->createSession('USD', '500');
        $foreignBranch = Branch::factory()->create(['currency' => 'USD']);
        $movement = $this->createMovement($session, PosCashDirection::In, PosCashReason::PayIn, '200');
        $movement->update(['branch_id' => $foreignBranch->id]);

        $closed = $this->closeSession($session, '500');

        $this->assertSame('500.00', $closed->meta['cash_reconciliation']['expected_cash']);
        $this->assertEquals(0, $closed->cash_over_short->amount());
    }

    public function test_rejected_variance_leaves_session_open_without_closing_side_effects(): void
    {
        $session = $this->createSession('USD', '500');

        try {
            $this->closeSession($session, '499');
            $this->fail('A variance above the configured threshold must reject closing.');
        } catch (ValidationException $exception) {
            $this->assertStringContainsString('500.00', $exception->errors()['declared_cash'][0]);
        }

        $this->assertSame(PosSessionStatus::Open, $session->fresh()->status);
        $this->assertNull($session->fresh()->declared_cash);
        $this->assertNull($session->fresh()->meta);
        $this->assertDatabaseCount('pos_cash_movements', 0);
        Event::assertNotDispatched(ClosePosSession::class);
    }

    public function test_snapshot_is_serialized_with_money_and_historical_sessions_are_not_recalculated(): void
    {
        $session = $this->createSession('JOD', '0.100');
        $closed = $this->closeSession($session, '0.100');
        $payload = json_decode((new PosSessionShowResource($closed))->toJson(), true);

        $this->assertEquals(0.1, $payload['cash_reconciliation']['expected_cash']['amount']);
        $this->assertSame('JOD', $payload['cash_reconciliation']['expected_cash']['currency']);
        $this->assertSame(3, $payload['cash_reconciliation']['expected_cash']['precision']);
        $this->assertArrayHasKey('system_cash_sales', $payload);
        $this->assertArrayHasKey('cash_over_short', $payload);
        $this->createMovement($closed, PosCashDirection::In, PosCashReason::PayIn, '1');
        $laterPayload = json_decode((new PosSessionShowResource($closed->fresh()))->toJson(), true);
        $this->assertEquals($payload['cash_reconciliation'], $laterPayload['cash_reconciliation']);

        $legacy = $this->createSession('USD', '500');
        $legacy->update(['status' => PosSessionStatus::Closed, 'declared_cash' => 777, 'cash_over_short' => 277]);
        $legacyPayload = json_decode((new PosSessionShowResource($legacy->fresh()))->toJson(), true);

        $this->assertNull($legacyPayload['cash_reconciliation']);
        $this->assertEquals(277, $legacyPayload['cash_over_short']['amount']);
        $this->assertNull($legacy->fresh()->meta);
    }

    public static function currencyRoundingCases(): iterable
    {
        yield 'JPY' => ['JPY', '0.5000', '0.4000', '1'];
        yield 'USD' => ['USD', '0.0050', '0.0040', '0.01'];
        yield 'JOD' => ['JOD', '0.0005', '0.0004', '0.001'];
    }

    #[DataProvider('currencyRoundingCases')]
    public function test_method_net_and_summary_totals_use_the_same_currency_rounded_components(string $currency, string $receipt, string $refund, string $unit): void
    {
        $session = $this->createSession($currency, '0');
        $order = $this->createOrder($session, '100');
        foreach (PaymentMethod::cases() as $method) {
            $this->createPayment($order, $method, $receipt);
            $this->createPayment($order, $method, $refund, PaymentType::Refund);
        }

        $closed = $this->closeSession($session, '0');
        $scale = Currency::subunit($currency);
        foreach ($closed->meta['payment_reconciliation']['methods'] as $method) {
            $this->assertSame($unit, $method['collections']);
            $this->assertSame((string) BigDecimal::zero()->toScale($scale), $method['refunds']);
            $this->assertSame($unit, $method['net']);
        }
        foreach (['system_cash_sales' => 1, 'system_card_sales' => 1, 'system_other_sales' => 4, 'total_sales' => 6, 'total_refunds' => 0] as $column => $multiplier) {
            $this->assertTrue(BigDecimal::of((string) $closed->getRawOriginal($column))->isEqualTo(BigDecimal::of($unit)->multipliedBy($multiplier)));
        }
        $payload = json_decode((new PosSessionShowResource($closed))->toJson(), true);
        $this->assertEquals($payload['total_sales'], $payload['total_collections']);
        $this->assertSame($scale, $payload['total_collections']['precision']);
        $this->assertSame(2, $closed->meta['payment_reconciliation']['version']);
    }

    #[DataProvider('currencyRoundingCases')]
    public function test_expected_cash_matches_rounded_opening_inflows_and_outflows(string $currency, string $receipt, string $refund, string $unit): void
    {
        $session = $this->createSession($currency, $receipt);
        $this->createMovement($session, PosCashDirection::In, PosCashReason::PayIn, $receipt);
        $this->createMovement($session, PosCashDirection::Out, PosCashReason::PayOut, $refund);
        $expected = (string) BigDecimal::of($unit)->multipliedBy(2);

        $closed = $this->closeSession($session, $expected);

        $this->assertSame($unit, $closed->meta['cash_reconciliation']['opening_float']);
        $this->assertSame($unit, $closed->meta['cash_reconciliation']['cash_in']);
        $this->assertSame($expected, $closed->meta['cash_reconciliation']['expected_cash']);
        $this->assertTrue(BigDecimal::of((string) $closed->getRawOriginal('opening_float'))->isEqualTo(BigDecimal::of($receipt)));
        $this->assertEquals(0, $closed->cash_over_short->amount());
        $payload = json_decode((new PosSessionShowResource($closed))->toJson(), true);
        $this->assertEquals((float) $unit, $payload['opening_float']['amount']);
        $this->assertEquals((float) $expected, $payload['cash_reconciliation']['expected_cash']['amount']);
    }

    #[DataProvider('currencyRoundingCases')]
    public function test_legacy_count_rounding_is_preserved_and_the_normalized_count_is_saved(string $currency, string $receipt, string $refund, string $unit): void
    {
        $session = $this->createSession($currency, '0');
        $session->branch->update(['cash_difference_threshold' => 1]);

        $closed = $this->closeSession($session, $receipt);

        $this->assertTrue(BigDecimal::of((string) $closed->getRawOriginal('declared_cash'))->isEqualTo(BigDecimal::of($unit)));
        $this->assertTrue(BigDecimal::of((string) $closed->getRawOriginal('cash_over_short'))->isEqualTo(BigDecimal::of($unit)));
        $adjustment = PosCashMovement::query()->where('pos_session_id', $closed->id)->where('reason', PosCashReason::ClosingAdjust)->sole();
        $this->assertTrue(BigDecimal::of((string) $adjustment->getRawOriginal('amount'))->isEqualTo(BigDecimal::of($unit)));
    }

    public function test_source_amounts_are_aggregated_before_currency_rounding(): void
    {
        $session = $this->createSession('USD', '0');
        $order = $this->createOrder($session, '100');
        for ($index = 0; $index < 2; $index++) {
            $this->createPayment($order, PaymentMethod::Cash, '0.004');
            $this->createMovement($session, PosCashDirection::In, PosCashReason::Sale, '0.004');
        }

        $closed = $this->closeSession($session, '0.01');

        $this->assertEquals(0.01, $closed->system_cash_sales->amount());
        $this->assertSame('0.01', $closed->meta['cash_reconciliation']['cash_in']);
        $this->assertSame('0.01', $closed->meta['cash_reconciliation']['expected_cash']);
    }

    public function test_mysql_preserves_large_closing_counts_and_adjustment_decimal_balances(): void
    {
        if (DB::getDriverName() !== 'mysql') {
            $this->markTestSkipped('MySQL DECIMAL storage is required for large currency amounts.');
        }
        $this->assertStringEndsWith('_test', DB::getDatabaseName());
        $session = $this->createSession('JOD', '20000000000000.001');
        $session->branch->update(['cash_difference_threshold' => 1]);

        $closed = $this->closeSession($session, '20000000000000.002')->fresh();
        $adjustment = PosCashMovement::query()->where('pos_session_id', $closed->id)->where('reason', PosCashReason::ClosingAdjust)->sole();

        $this->assertSame('20000000000000.0020', $closed->getRawOriginal('declared_cash'));
        $this->assertSame('0.0010', $adjustment->getRawOriginal('amount'));
        $this->assertSame('20000000000000.0010', $adjustment->getRawOriginal('balance_before'));
        $this->assertSame($adjustment->getRawOriginal('balance_before'), $adjustment->getRawOriginal('balance_after'));
        $open = $this->createSession('JOD', '20000000000000.001');
        $explicit = app(PosCashMovementServiceInterface::class)->closingAdjust($open, '10000000000000.003');
        $this->assertSame('10000000000000.0030', $explicit->fresh()->getRawOriginal('amount'));
    }

    private function createSession(string $currency, string $opening): PosSession
    {
        Event::fake([ClosePosSession::class]);
        $branch = Branch::factory()->create(['currency' => $currency, 'cash_difference_threshold' => 0]);
        $user = User::factory()->create(['branch_id' => $branch->id, 'is_active' => true]);
        $register = PosRegister::factory()->create(['branch_id' => $branch->id, 'is_active' => true]);
        $this->actingAs($user);

        return PosSession::factory()->create([
            'branch_id' => $branch->id,
            'pos_register_id' => $register->id,
            'opened_by' => $user->id,
            'opened_at' => now(),
            'opening_float' => $opening,
        ])->load('branch');
    }

    private function createOrder(PosSession $session, string $amount): Order
    {
        return Order::query()->create([
            'branch_id' => $session->branch_id,
            'pos_register_id' => $session->pos_register_id,
            'pos_session_id' => $session->id,
            'status' => OrderStatus::Completed,
            'type' => OrderType::Takeaway,
            'payment_status' => OrderPaymentStatus::Paid,
            'currency' => $session->currency,
            'currency_rate' => 1,
            'subtotal' => $amount,
            'total' => $amount,
            'due_amount' => 0,
            'order_date' => now()->toDateString(),
        ]);
    }

    private function createPayment(Order $order, PaymentMethod $method, string $amount, PaymentType $type = PaymentType::Payment): Payment
    {
        return $order->payments()->create([
            'branch_id' => $order->branch_id,
            'pos_session_id' => $order->pos_session_id,
            'order_reference_no' => $order->reference_no,
            'method' => $method,
            'amount' => $amount,
            'type' => $type,
            'currency' => $order->currency,
            'currency_rate' => 1,
            'received_at' => now(),
        ]);
    }

    private function createMovement(PosSession $session, PosCashDirection $direction, PosCashReason $reason, string $amount, ?Payment $payment = null): PosCashMovement
    {
        return PosCashMovement::factory()->create([
            'branch_id' => $session->branch_id,
            'pos_session_id' => $session->id,
            'pos_register_id' => $session->pos_register_id,
            'currency' => $session->currency,
            'direction' => $direction,
            'reason' => $reason,
            'amount' => $amount,
            'payment_id' => $payment?->id,
            'order_id' => $payment?->order_id,
        ]);
    }

    private function closeSession(PosSession $session, string $declared): PosSession
    {
        return app(PosSessionServiceInterface::class)->close($session->id, ['declared_cash' => $declared]);
    }
}
