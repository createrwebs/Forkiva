<?php

namespace Modules\Pos\Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Mockery\MockInterface;
use Modules\Branch\Models\Branch;
use Modules\Order\Enums\OrderPaymentStatus;
use Modules\Order\Enums\OrderStatus;
use Modules\Order\Enums\OrderType;
use Modules\Order\Models\Order;
use Modules\Payment\Enums\PaymentMethod;
use Modules\Payment\Enums\PaymentType;
use Modules\Pos\Enums\PosCashDirection;
use Modules\Pos\Enums\PosCashReason;
use Modules\Pos\Events\ClosePosSession;
use Modules\Pos\Models\PosRegister;
use Modules\Pos\Models\PosSession;
use Modules\Pos\Services\PosCashMovement\PosCashMovementService;
use Modules\Pos\Services\PosCashMovement\PosCashMovementServiceInterface;
use Modules\Pos\Services\PosSession\PosSessionServiceInterface;
use Modules\User\Models\User;
use RuntimeException;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

class PosSessionWriterSafetyTest extends TestCase
{
    use RefreshDatabase;

    public function test_stale_sessions_cannot_accept_receipts_or_refunds_for_any_method(): void
    {
        [$session, $order] = $this->context();
        app(PosSessionServiceInterface::class)->close($session->id, ['declared_cash' => '500']);
        $snapshot = $session->fresh()->getRawOriginal();

        foreach (PaymentMethod::cases() as $method) {
            foreach (PaymentType::cases() as $type) {
                $this->assertConflict(fn () => $order->storePayment([
                    'method' => $method->value,
                    'type' => $type,
                    'amount' => '10',
                    'session' => $session,
                ]));
            }
        }

        $this->assertDatabaseCount('payments', 0);
        $this->assertDatabaseCount('pos_cash_movements', 0);
        $this->assertSame($snapshot, $session->fresh()->getRawOriginal());
    }

    public function test_receipt_and_drawer_effect_roll_back_together_without_a_callers_transaction(): void
    {
        [$session, $order] = $this->context();
        $this->mock(PosCashMovementServiceInterface::class, function (MockInterface $mock): void {
            $mock->shouldReceive('sale')->once()->andThrow(new RuntimeException('Drawer unavailable'));
        });

        try {
            $order->storePayment(['method' => 'cash', 'amount' => '10', 'session' => $session]);
            $this->fail('The failed movement must reject the receipt.');
        } catch (RuntimeException $exception) {
            $this->assertSame('Drawer unavailable', $exception->getMessage());
        }

        $this->assertDatabaseCount('payments', 0);
        $this->assertDatabaseCount('pos_cash_movements', 0);
    }

    public function test_every_new_drawer_movement_including_adjustments_rejects_closed_sessions(): void
    {
        [$session] = $this->context();
        app(PosSessionServiceInterface::class)->close($session->id, ['declared_cash' => '500']);
        $service = app(PosCashMovementService::class);

        foreach (PosCashReason::cases() as $reason) {
            $this->assertConflict(fn () => $service->addMovement($session, $reason, '10', PosCashDirection::In));
        }

        $this->assertDatabaseCount('pos_cash_movements', 0);
    }

    public function test_matching_movement_retry_replays_after_close_and_changed_details_still_conflict(): void
    {
        [$session] = $this->context();
        $service = app(PosCashMovementService::class);
        $movement = $service->addMovement($session, PosCashReason::PayIn, '10', PosCashDirection::In, idempotencyKey: 'drawer-retry');
        app(PosSessionServiceInterface::class)->close($session->id, ['declared_cash' => '510']);

        $replay = $service->addMovement($session, PosCashReason::PayIn, '10.0000', PosCashDirection::In, idempotencyKey: 'drawer-retry');
        $this->assertSame($movement->id, $replay->id);
        try {
            $service->addMovement($session, PosCashReason::PayIn, '11', PosCashDirection::In, idempotencyKey: 'drawer-retry');
            $this->fail('A reused key must not change the amount.');
        } catch (HttpException $exception) {
            $this->assertSame(422, $exception->getStatusCode());
        }
        $this->assertDatabaseCount('pos_cash_movements', 1);
    }

    public function test_voiding_attributed_or_legacy_cash_checks_the_collecting_session(): void
    {
        foreach ([true, false] as $explicit) {
            [$session, $order] = $this->context();
            $payment = $order->storePayment(['method' => 'cash', 'amount' => '10', 'session' => $session]);
            if (! $explicit) {
                $payment->update(['pos_session_id' => null]);
            }
            app(PosSessionServiceInterface::class)->close($session->id, ['declared_cash' => '510']);
            $this->assertConflict(fn () => $order->voidPayment($payment, 'Correction'));
            $this->assertFalse($payment->fresh()->isVoided());
            $this->assertSame('510.00', $session->fresh()->meta['cash_reconciliation']['expected_cash']);
        }
    }

    public function test_completed_void_can_replay_after_close_without_altering_its_original_evidence(): void
    {
        [$session, $order] = $this->context();
        $payment = $order->storePayment(['method' => 'card', 'amount' => '10', 'session' => $session]);
        $order->voidPayment($payment, 'Original reason');
        $snapshot = $payment->fresh()->getRawOriginal();
        app(PosSessionServiceInterface::class)->close($session->id, ['declared_cash' => '500']);
        $order->voidPayment($payment, 'Different retry reason');

        $this->assertSame($snapshot, $payment->fresh()->getRawOriginal());
        $this->assertEquals(0, $session->fresh()->total_sales->amount());
    }

    public function test_independent_unassigned_deposit_application_preserves_closed_session_snapshot(): void
    {
        [$session, $order] = $this->context();
        app(PosSessionServiceInterface::class)->close($session->id, ['declared_cash' => '500']);
        $snapshot = $session->fresh()->getRawOriginal();
        $payment = $order->storePayment(['method' => 'reservation_deposit', 'amount' => '10']);
        $order->voidPayment($payment, 'Application reversed');

        $this->assertNull($payment->pos_session_id);
        $this->assertTrue($payment->fresh()->isVoided());
        $this->assertSame($snapshot, $session->fresh()->getRawOriginal());
        $this->assertDatabaseCount('pos_cash_movements', 0);
    }

    private function assertConflict(callable $action): void
    {
        try {
            $action();
            $this->fail('The closed session must reject a new financial mutation.');
        } catch (HttpException $exception) {
            $this->assertSame(409, $exception->getStatusCode());
        }
    }

    private function context(): array
    {
        Event::fake([ClosePosSession::class]);
        $branch = Branch::factory()->create(['currency' => 'USD', 'cash_difference_threshold' => 10]);
        $user = User::factory()->create(['branch_id' => $branch->id, 'is_active' => true]);
        $register = PosRegister::factory()->create(['branch_id' => $branch->id, 'is_active' => true]);
        $this->actingAs($user);
        $session = PosSession::factory()->create([
            'branch_id' => $branch->id,
            'pos_register_id' => $register->id,
            'opened_by' => $user->id,
            'opened_at' => now(),
            'opening_float' => '500',
        ]);
        $order = Order::query()->create([
            'branch_id' => $branch->id,
            'pos_register_id' => $register->id,
            'pos_session_id' => $session->id,
            'status' => OrderStatus::Completed,
            'type' => OrderType::Takeaway,
            'payment_status' => OrderPaymentStatus::Unpaid,
            'currency' => 'USD',
            'currency_rate' => 1,
            'subtotal' => 50,
            'total' => 50,
            'due_amount' => 50,
            'order_date' => now()->toDateString(),
        ]);

        return [$session, $order];
    }
}
