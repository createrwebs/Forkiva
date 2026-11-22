<?php

namespace Modules\Pos\Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Modules\Branch\Models\Branch;
use Modules\Order\Enums\OrderPaymentStatus;
use Modules\Order\Enums\OrderStatus;
use Modules\Order\Enums\OrderType;
use Modules\Order\Events\OrderVoided;
use Modules\Order\Listeners\OrderRefundAmount;
use Modules\Order\Models\Order;
use Modules\Order\Services\OrderPayment\OrderPaymentServiceInterface;
use Modules\Order\Transformers\Api\V1\OrderPaymentResource;
use Modules\Payment\Enums\PaymentMethod;
use Modules\Payment\Enums\PaymentType;
use Modules\Payment\Enums\RefundPaymentMethod;
use Modules\Payment\Models\Payment;
use Modules\Pos\Enums\PosCashDirection;
use Modules\Pos\Enums\PosCashReason;
use Modules\Pos\Enums\PosSessionStatus;
use Modules\Pos\Events\ClosePosSession;
use Modules\Pos\Models\PosCashMovement;
use Modules\Pos\Models\PosRegister;
use Modules\Pos\Models\PosSession;
use Modules\Pos\Services\PosSession\PosSessionServiceInterface;
use Modules\Pos\Transformers\Api\V1\PosSessionShowResource;
use Modules\User\Models\Permission;
use Modules\User\Models\User;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

class PosSessionPaymentAttributionTest extends TestCase
{
    use RefreshDatabase;

    public function test_older_order_receipts_are_assigned_to_receiving_session_without_changing_original_closing(): void
    {
        $original = $this->createSession();
        $order = $this->createOrder($original);
        $original = $this->closeSession($original, '500');
        $snapshot = $original->fresh()->getRawOriginal();
        $receiving = $this->createSession($original);

        foreach (['cash' => '100', 'card' => '70', 'mobile_wallet' => '30'] as $method => $amount) {
            $payment = $order->storePayment(['method' => $method, 'amount' => $amount, 'session' => $receiving]);
            $this->assertSame($receiving->id, $payment->pos_session_id);
        }
        $closed = $this->closeSession($receiving, '600');

        $this->assertEquals(100, $closed->system_cash_sales->amount());
        $this->assertEquals(70, $closed->system_card_sales->amount());
        $this->assertEquals(30, $closed->system_other_sales->amount());
        $this->assertEquals(200, $closed->total_sales->amount());
        $this->assertSame(0, $closed->orders_count);
        $this->assertSame($original->id, $order->fresh()->pos_session_id);
        $this->assertSame($snapshot, $original->fresh()->getRawOriginal());
        $payload = json_decode((new PosSessionShowResource($closed))->toJson(), true);
        $this->assertEquals(200, $payload['total_collections']['amount']);
    }

    public function test_partial_payments_through_order_service_are_split_between_the_collecting_sessions(): void
    {
        $first = $this->createSession();
        $order = $this->createOrder($first);
        $order->update(['payment_status' => OrderPaymentStatus::Unpaid, 'due_amount' => '200']);
        $service = app(OrderPaymentServiceInterface::class);
        $service->storePayment($order->id, ['session_id' => $first->id, 'payment_mode' => 'partial', 'payments' => [['method' => 'card', 'amount' => 80]]]);
        $first = $this->closeSession($first, '500');
        $second = $this->createSession($first);
        $service->storePayment($order->id, ['session_id' => $second->id, 'payment_mode' => 'full', 'payments' => [['method' => 'bank_transfer', 'amount' => 120]]]);
        $second = $this->closeSession($second, '500');

        $this->assertEquals(80, $first->system_card_sales->amount());
        $this->assertEquals(120, $second->system_other_sales->amount());
        $this->assertEquals(0, $order->fresh()->due_amount->amount());
        $this->assertEquals([$first->id, $second->id], $order->payments()->orderBy('id')->pluck('pos_session_id')->all());
    }

    public function test_refund_for_a_closed_sessions_order_is_counted_only_in_refunding_session(): void
    {
        $original = $this->createSession();
        $order = $this->createOrder($original);
        $order->storePayment(['method' => 'card', 'amount' => '200', 'session' => $original]);
        $original = $this->closeSession($original, '500');
        $receiving = $this->createSession($original);
        $refund = $order->storePayment(['method' => 'cash', 'amount' => '25', 'type' => 'refund', 'session' => $receiving]);
        $closed = $this->closeSession($receiving, '475');

        $this->assertSame($receiving->id, $refund->pos_session_id);
        $this->assertEquals(0, $closed->total_sales->amount());
        $this->assertEquals(25, $closed->total_refunds->amount());
        $this->assertSame('-25.00', $this->methodRow($closed, 'cash')['net']);
        $this->assertEquals(0, $original->fresh()->total_refunds->amount());
    }

    public function test_order_refund_listener_records_the_refunding_session_for_non_cash_refunds(): void
    {
        $original = $this->createSession();
        $order = $this->createOrder($original);
        $order->storePayment(['method' => 'card', 'amount' => '200', 'session' => $original]);
        $this->closeSession($original, '500');
        $receiving = $this->createSession($original);

        app(OrderRefundAmount::class)->handle(new OrderVoided(
            order: $order,
            status: OrderStatus::Refunded,
            refundPaymentMethod: RefundPaymentMethod::BankTransfer,
            posSession: $receiving,
        ));

        $refund = $order->payments()->where('type', 'refund')->firstOrFail();
        $this->assertSame($receiving->id, $refund->pos_session_id);
        $this->assertEquals(200, $this->closeSession($receiving, '500')->total_refunds->amount());
    }

    public function test_all_supported_methods_have_separate_collection_refund_and_net_totals(): void
    {
        $session = $this->createSession();
        $order = $this->createOrder($session);
        foreach (PaymentMethod::cases() as $method) {
            $this->createPayment($order, $session, $method, '10');
            $this->createPayment($order, $session, $method, '2', PaymentType::Refund);
        }
        $closed = $this->closeSession($session, '500');

        $this->assertCount(6, $closed->meta['payment_reconciliation']['methods']);
        foreach (PaymentMethod::cases() as $method) {
            $row = $this->methodRow($closed, $method->value);
            $this->assertSame('10.00', $row['collections']);
            $this->assertSame('2.00', $row['refunds']);
            $this->assertSame('8.00', $row['net']);
        }
        $this->assertEquals(60, $closed->total_sales->amount());
        $this->assertEquals(12, $closed->total_refunds->amount());
        $this->assertEquals(40, $closed->system_other_sales->amount());
        auth()->user()->givePermissionTo(Permission::findOrCreate('admin.pos_sessions.show'));
        $response = $this->getJson('/api/v1/pos/sessions/'.$closed->id)->assertOk()
            ->assertJsonPath('body.system_other_sales.amount', 40)
            ->assertJsonCount(6, 'body.payment_reconciliation.methods');
        foreach (PaymentMethod::cases() as $index => $method) {
            $response->assertJsonPath('body.payment_reconciliation.methods.'.$index.'.method.id', $method->value)
                ->assertJsonPath('body.payment_reconciliation.methods.'.$index.'.collections.amount', 10)
                ->assertJsonPath('body.payment_reconciliation.methods.'.$index.'.refunds.amount', 2)
                ->assertJsonPath('body.payment_reconciliation.methods.'.$index.'.net.amount', 8);
        }
    }

    public function test_unknown_legacy_payment_is_excluded_and_disclosed_without_backfilling(): void
    {
        $session = $this->createSession();
        $order = $this->createOrder($session);
        $payment = $this->createPayment($order, null, PaymentMethod::Card, '200');
        $closed = $this->closeSession($session, '500');

        $this->assertEquals(0, $closed->total_sales->amount());
        $this->assertSame(1, $closed->meta['payment_reconciliation']['unattributed_order_payments_count']);
        $this->assertNull($payment->fresh()->pos_session_id);
    }

    public function test_legacy_cash_uses_unambiguous_movement_evidence_without_double_counting(): void
    {
        $session = $this->createSession();
        $order = $this->createOrder($session);
        $payment = $this->createPayment($order, null, PaymentMethod::Cash, '200');
        $this->createMovement($session, $payment);
        $this->createMovement($session, $payment);
        $closed = $this->closeSession($session, '900');

        $this->assertEquals(200, $closed->system_cash_sales->amount());
        $this->assertSame(1, $closed->meta['payment_reconciliation']['legacy_cash_payments_count']);
        $this->assertSame(0, $closed->meta['payment_reconciliation']['unattributed_order_payments_count']);
        $this->assertNull($payment->fresh()->pos_session_id);
    }

    public function test_ambiguous_legacy_cash_is_not_assigned_to_two_sessions(): void
    {
        $session = $this->createSession();
        $other = $this->createSession($session);
        $order = $this->createOrder($session);
        $payment = $this->createPayment($order, null, PaymentMethod::Cash, '200');
        $this->createMovement($session, $payment);
        $this->createMovement($other, $payment);

        $closed = $this->closeSession($session, '700');
        $this->assertEquals(0, $closed->total_sales->amount());
        $this->assertSame(1, $closed->meta['payment_reconciliation']['unattributed_order_payments_count']);
        $this->assertEquals(0, $this->closeSession($other, '700')->total_sales->amount());
    }

    public function test_explicit_attribution_takes_precedence_over_legacy_drawer_links(): void
    {
        $session = $this->createSession();
        $other = $this->createSession($session);
        $order = $this->createOrder($session);
        $payment = $this->createPayment($order, $other, PaymentMethod::Cash, '200');
        $this->createMovement($session, $payment);

        $this->assertEquals(0, $this->closeSession($session, '700')->total_sales->amount());
        $this->assertEquals(200, $this->closeSession($other, '500')->total_sales->amount());
    }

    public function test_mismatched_payment_currency_is_disclosed_and_not_added_to_session_totals(): void
    {
        $session = $this->createSession();
        $order = $this->createOrder($session);
        $order->update(['currency' => 'JOD']);
        try {
            $order->storePayment(['method' => 'cash', 'amount' => '200', 'session' => $session]);
            $this->fail('A mismatched currency must not be recorded as local drawer cash.');
        } catch (HttpException $exception) {
            $this->assertSame(422, $exception->getStatusCode());
        }
        $this->assertDatabaseCount('payments', 0);
        $order->update(['currency' => 'USD']);
        $payment = $this->createPayment($order, $session, PaymentMethod::Card, '200');
        $payment->update(['currency' => 'JOD']);
        $closed = $this->closeSession($session, '500');

        $this->assertEquals(0, $closed->total_sales->amount());
        $this->assertSame(1, $closed->meta['payment_reconciliation']['currency_mismatch_payments_count']);
    }

    public function test_payment_without_pos_context_does_not_inherit_the_orders_session(): void
    {
        $session = $this->createSession();
        $order = $this->createOrder($session);
        $payment = $order->storePayment(['method' => 'card', 'amount' => '200', 'skip_pos_cash_movement' => true]);

        $this->assertNull($payment->pos_session_id);
    }

    public function test_closed_or_foreign_branch_session_is_rejected_before_payment_is_created(): void
    {
        $session = $this->createSession();
        $order = $this->createOrder($session);
        $session->update(['status' => PosSessionStatus::Closed]);
        $foreign = $this->createSession();
        $this->actingAs(User::factory()->make(['branch_id' => null]));
        foreach ([$session, $foreign] as $invalid) {
            try {
                $order->storePayment(['method' => 'card', 'amount' => '200', 'session' => $invalid]);
                $this->fail('An invalid collecting session must reject the payment.');
            } catch (HttpException $exception) {
                $this->assertSame(409, $exception->getStatusCode());
            }
        }
        $this->assertDatabaseCount('payments', 0);
    }

    public function test_detail_api_preserves_money_and_nullable_legacy_contracts(): void
    {
        $session = $this->createSession();
        $user = auth()->user();
        foreach (['admin.pos_sessions.show', 'admin.payments.show'] as $permission) {
            $user->givePermissionTo(Permission::findOrCreate($permission));
        }
        $order = $this->createOrder($session);
        $payment = $order->storePayment(['method' => 'card', 'amount' => '200', 'session' => $session]);
        $closed = $this->closeSession($session, '500');

        $serializedOrderPayment = json_decode((new OrderPaymentResource($payment))->toJson(), true);
        $this->assertSame($session->id, $serializedOrderPayment['pos_session_id']);
        $this->getJson('/api/v1/payments/'.$payment->id)->assertOk()->assertJsonPath('body.pos_session_id', $session->id);
        $this->getJson('/api/v1/pos/sessions/'.$closed->id)->assertOk()
            ->assertJsonPath('body.payment_reconciliation.methods.1.method.id', 'card')
            ->assertJsonPath('body.payment_reconciliation.methods.1.collections.amount', 200)
            ->assertJsonPath('body.payment_reconciliation.methods.1.collections.currency', 'USD')
            ->assertJsonPath('body.payment_reconciliation.methods.1.collections.precision', 2);
        $legacy = $this->createSession($session);
        $legacy->update(['status' => PosSessionStatus::Closed, 'system_card_sales' => 999]);
        $this->getJson('/api/v1/pos/sessions/'.$legacy->id)->assertOk()
            ->assertJsonPath('body.payment_reconciliation', null)
            ->assertJsonPath('body.system_card_sales.amount', 999);
    }

    public function test_detail_api_requires_permission_and_rejects_foreign_branch_access(): void
    {
        $session = $this->createSession();
        $user = auth()->user();
        $this->getJson('/api/v1/pos/sessions/'.$session->id)->assertForbidden();
        $user->givePermissionTo(Permission::findOrCreate('admin.pos_sessions.show'));
        $foreign = $this->createSession();
        $this->actingAs($user);
        $this->getJson('/api/v1/pos/sessions/'.$foreign->id)->assertNotFound();
    }

    private function createSession(?PosSession $existing = null): PosSession
    {
        Event::fake([ClosePosSession::class]);
        if (! $existing) {
            $this->app['auth']->forgetGuards();
        }
        $branch = $existing?->branch ?: Branch::factory()->create(['currency' => 'USD', 'cash_difference_threshold' => 0]);
        $user = $existing?->openedBy ?: User::factory()->create(['branch_id' => $branch->id, 'is_active' => true]);
        $this->actingAs($user);
        $register = PosRegister::factory()->create(['branch_id' => $branch->id]);

        return PosSession::factory()->create([
            'branch_id' => $branch->id,
            'pos_register_id' => $register->id,
            'opened_by' => $user->id,
            'opened_at' => now(),
            'opening_float' => '500',
        ])->load('branch');
    }

    private function createOrder(PosSession $session): Order
    {
        return Order::query()->create([
            'branch_id' => $session->branch_id,
            'pos_register_id' => $session->pos_register_id,
            'pos_session_id' => $session->id,
            'status' => OrderStatus::Completed,
            'payment_status' => OrderPaymentStatus::Paid,
            'type' => OrderType::Takeaway,
            'currency' => 'USD',
            'currency_rate' => 1,
            'subtotal' => '200',
            'total' => '200',
            'due_amount' => 0,
            'order_date' => now(),
        ]);
    }

    private function createPayment(Order $order, ?PosSession $session, PaymentMethod $method, string $amount, PaymentType $type = PaymentType::Payment): Payment
    {
        return $order->payments()->create([
            'branch_id' => $order->branch_id,
            'pos_session_id' => $session?->id,
            'order_reference_no' => $order->reference_no,
            'method' => $method,
            'amount' => $amount,
            'type' => $type,
            'currency' => $order->currency,
            'currency_rate' => 1,
        ]);
    }

    private function createMovement(PosSession $session, Payment $payment): void
    {
        PosCashMovement::factory()->create([
            'branch_id' => $session->branch_id,
            'pos_session_id' => $session->id,
            'pos_register_id' => $session->pos_register_id,
            'currency' => 'USD',
            'payment_id' => $payment->id,
            'order_id' => $payment->order_id,
            'direction' => PosCashDirection::In,
            'reason' => PosCashReason::Sale,
            'amount' => '200',
        ]);
    }

    private function closeSession(PosSession $session, string $cash): PosSession
    {
        return app(PosSessionServiceInterface::class)->close($session->id, ['declared_cash' => $cash]);
    }

    private function methodRow(PosSession $session, string $method): array
    {
        return collect($session->meta['payment_reconciliation']['methods'])->firstWhere('method', $method);
    }
}
