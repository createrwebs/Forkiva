<?php

namespace Modules\Pos\Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Modules\Branch\Models\Branch;
use Modules\Order\Enums\OrderPaymentStatus;
use Modules\Order\Enums\OrderStatus;
use Modules\Order\Enums\OrderType;
use Modules\Order\Models\Order;
use Modules\Payment\Enums\PaymentMethod;
use Modules\Payment\Enums\PaymentType;
use Modules\Pos\Enums\PosCashDirection;
use Modules\Pos\Enums\PosCashReason;
use Modules\Pos\Enums\PosSessionStatus;
use Modules\Pos\Events\ClosePosSession;
use Modules\Pos\Models\PosCashMovement;
use Modules\Pos\Models\PosRegister;
use Modules\Pos\Models\PosSession;
use Modules\User\Models\User;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class PosSessionClosingReviewTest extends TestCase
{
    use RefreshDatabase;

    public function test_preview_is_read_only_and_keeps_every_payment_method_separate(): void
    {
        $session = $this->createSession();
        $order = Order::query()->create([
            'branch_id' => $session->branch_id, 'pos_register_id' => $session->pos_register_id,
            'pos_session_id' => $session->id, 'status' => OrderStatus::Completed,
            'type' => OrderType::Takeaway, 'payment_status' => OrderPaymentStatus::Paid,
            'currency' => 'USD', 'currency_rate' => 1, 'subtotal' => 600, 'total' => 600,
            'due_amount' => 0, 'order_date' => now()->toDateString(),
        ]);
        foreach (PaymentMethod::cases() as $method) {
            $order->payments()->create([
                'branch_id' => $session->branch_id, 'pos_session_id' => $session->id,
                'order_reference_no' => $order->reference_no, 'method' => $method,
                'type' => PaymentType::Payment, 'amount' => 100, 'currency' => 'USD',
                'currency_rate' => 1, 'received_at' => now(),
            ]);
        }
        $this->movement($session, '100', PosCashDirection::In);
        $this->movement($session, '20');
        $before = $session->fresh()->getRawOriginal();
        $this->preview($session)->assertOk()
            ->assertJsonPath('body.review.cash_difference', null)
            ->assertJsonPath('body.review.can_close', false)
            ->assertJsonPath('body.review.token', null)
            ->assertJsonPath('body.declared_cash', null)
            ->assertJsonPath('body.cash_reconciliation.expected_cash.amount', 580)
            ->assertJsonPath('body.total_sales.amount', 600)
            ->assertJsonPath('body.total_collections.amount', 600)
            ->assertJsonCount(6, 'body.payment_reconciliation.methods');
        $this->preview($session, '575')->assertOk()
            ->assertJsonPath('body.review.cash_difference.amount', -5)
            ->assertJsonPath('body.review.allowed_difference.amount', 10)
            ->assertJsonPath('body.review.can_close', true);
        $this->assertSame($before, $session->fresh()->getRawOriginal());
        $this->assertDatabaseCount('pos_cash_movements', 2);
        $this->assertDatabaseCount('payments', 6);
        Event::assertNotDispatched(ClosePosSession::class);
    }

    public function test_variance_outside_policy_is_reviewable_but_cannot_be_confirmed(): void
    {
        $session = $this->createSession();
        $this->preview($session, '450')->assertOk()
            ->assertJsonPath('body.review.cash_difference.amount', -50)
            ->assertJsonPath('body.review.can_close', false)
            ->assertJsonPath('body.review.token', null);
        $this->assertDatabaseCount('pos_cash_movements', 0);
        $this->assertSame(PosSessionStatus::Open, $session->fresh()->status);
    }

    public function test_reviewed_close_saves_audit_and_adjustment_only_on_confirmation(): void
    {
        $session = $this->createSession();
        $token = $this->preview($session, '495')->assertOk()->json('body.review.token');
        $this->assertDatabaseCount('pos_cash_movements', 0);
        $this->putJson($this->url($session).'/close', ['declared_cash' => '495.00', 'review_token' => $token, 'variance_reason' => 'Drawer recounted; shortage confirmed.'])->assertOk();
        $closed = $session->fresh();
        $this->assertSame(PosSessionStatus::Closed, $closed->status);
        $this->assertSame('500.00', $closed->meta['cash_reconciliation']['expected_cash']);
        $this->assertSame(auth()->id(), $closed->meta['closing_review']['verified_by']);
        $this->assertDatabaseCount('pos_cash_movements', 1);
        $this->putJson($this->url($session).'/close', ['declared_cash' => '495', 'review_token' => $token])->assertStatus(400);
        $this->assertDatabaseCount('pos_cash_movements', 1);
        Event::assertDispatchedTimes(ClosePosSession::class, 1);
    }

    public function test_new_cash_movement_invalidates_review_without_closing(): void
    {
        $session = $this->createSession();
        $token = $this->preview($session, '500')->assertOk()->json('body.review.token');
        $this->movement($session, '5');
        $this->assertStale($session, '500', $token);
        $this->assertDatabaseCount('pos_cash_movements', 1);
        $fresh = $this->preview($session, '495')->assertOk()->json('body.review.token');
        $this->putJson($this->url($session).'/close', ['declared_cash' => '495', 'review_token' => $fresh, 'variance_reason' => 'Drawer recounted after cash movement.'])->assertOk();
    }

    public function test_new_non_cash_receipt_or_refund_invalidates_review(): void
    {
        $session = $this->createSession();
        $order = Order::query()->create([
            'branch_id' => $session->branch_id, 'pos_register_id' => $session->pos_register_id,
            'pos_session_id' => $session->id, 'status' => OrderStatus::Completed,
            'type' => OrderType::Takeaway, 'payment_status' => OrderPaymentStatus::Paid,
            'currency' => 'USD', 'currency_rate' => 1, 'subtotal' => 100, 'total' => 100,
            'due_amount' => 0, 'order_date' => now()->toDateString(),
        ]);
        foreach ([PaymentType::Payment, PaymentType::Refund] as $type) {
            $token = $this->preview($session, '500')->assertOk()->json('body.review.token');
            $order->payments()->create([
                'branch_id' => $session->branch_id, 'pos_session_id' => $session->id,
                'order_reference_no' => $order->reference_no, 'method' => PaymentMethod::BankTransfer,
                'type' => $type, 'amount' => 10, 'currency' => 'USD',
                'currency_rate' => 1, 'received_at' => now(),
            ]);
            $this->assertStale($session, '500', $token);
        }
        $this->assertDatabaseCount('pos_cash_movements', 0);
    }

    public function test_changed_policy_or_count_invalidates_review(): void
    {
        $session = $this->createSession();
        $token = $this->preview($session, '500')->assertOk()->json('body.review.token');
        $this->assertStale($session, '499', $token);
        $session->branch->update(['cash_difference_threshold' => 20]);
        $this->assertStale($session, '500', $token);
        $this->assertDatabaseCount('pos_cash_movements', 0);
    }

    public function test_token_is_bound_to_cashier_and_session(): void
    {
        $session = $this->createSession();
        $token = $this->preview($session, '500')->assertOk()->json('body.review.token');
        $other = User::factory()->create(['branch_id' => $session->branch_id, 'is_active' => true]);
        $other->givePermissionTo(Permission::findOrCreate('admin.pos_sessions.close'));
        $other->givePermissionTo(Permission::findOrCreate('admin.pos_sessions.close_others'));
        $this->actingAs($other);
        $this->assertStale($session, '500', $token);
        $second = PosSession::factory()->create([
            'branch_id' => $session->branch_id, 'pos_register_id' => $session->pos_register_id,
            'opened_by' => $other->id, 'opened_at' => now(), 'opening_float' => 500,
        ]);
        $this->assertStale($second, '500', $token);
    }

    public function test_preview_enforces_currency_precision_and_accepts_zero(): void
    {
        foreach ([['USD', '0.001'], ['JPY', '0.1']] as [$currency, $invalid]) {
            $session = $this->createSession($currency, '0');
            $this->preview($session, $invalid)->assertUnprocessable()->assertJsonValidationErrors('declared_cash');
            $this->preview($session, '0')->assertOk()->assertJsonPath('body.review.can_close', true);
        }
        $session = $this->createSession('JOD', '0.100');
        $this->preview($session, '0.101')->assertOk()
            ->assertJsonPath('body.review.declared_cash_decimal', '0.101')
            ->assertJsonPath('body.review.cash_difference.amount', 0.001)
            ->assertJsonPath('body.review.cash_difference.precision', 3);
    }

    public function test_invalid_count_and_token_are_rejected(): void
    {
        $session = $this->createSession();
        foreach (['-1', 'hello', '100000000000000'] as $invalid) {
            $this->preview($session, $invalid)->assertUnprocessable()->assertJsonValidationErrors('declared_cash');
        }
        $this->putJson($this->url($session).'/close', ['declared_cash' => '500', 'review_token' => 'invalid'])
            ->assertUnprocessable()->assertJsonValidationErrors('review_token');
        $this->assertStale($session, '500', str_repeat('0', 64));
    }

    public function test_preview_requires_close_permission_and_branch_access(): void
    {
        $session = $this->createSession();
        $owner = auth()->user();
        $owner->revokePermissionTo('admin.pos_sessions.close');
        $this->preview($session)->assertForbidden();
        $owner->givePermissionTo('admin.pos_sessions.close');
        $foreign = $this->createSession();
        $this->actingAs($owner);
        $this->preview($foreign)->assertNotFound();
        $session->update(['status' => PosSessionStatus::Closed]);
        $this->preview($session)->assertStatus(400);
    }

    public function test_existing_close_clients_can_still_close_without_a_review_token(): void
    {
        $session = $this->createSession();
        $this->putJson($this->url($session).'/close', ['declared_cash' => '500'])->assertOk();
        $this->assertSame(PosSessionStatus::Closed, $session->fresh()->status);
        $this->assertArrayNotHasKey('closing_review', $session->fresh()->meta);
    }

    public function test_reviewed_close_rejects_excess_precision_and_persists_the_exact_reviewed_count(): void
    {
        foreach ([['JPY', '0', '0.1'], ['USD', '0.00', '0.001'], ['JOD', '0.000', '0.0001']] as [$currency, $count, $invalid]) {
            $session = $this->createSession($currency, '0');
            $token = $this->preview($session, $count)->assertOk()->json('body.review.token');
            $this->putJson($this->url($session).'/close', ['declared_cash' => $invalid, 'review_token' => $token])
                ->assertUnprocessable()->assertJsonValidationErrors('declared_cash');
            $this->assertSame(PosSessionStatus::Open, $session->fresh()->status);
            $this->assertDatabaseCount('pos_cash_movements', 0);
            $this->putJson($this->url($session).'/close', ['declared_cash' => $count, 'review_token' => $token])->assertOk();
            $this->assertEquals(0, $session->fresh()->declared_cash->amount());
        }
    }

    private function createSession(string $currency = 'USD', string $opening = '500'): PosSession
    {
        Event::fake([ClosePosSession::class]);
        auth()->forgetGuards();
        $branch = Branch::factory()->create(['currency' => $currency, 'cash_difference_threshold' => 10]);
        $user = User::factory()->create(['branch_id' => $branch->id, 'is_active' => true]);
        $register = PosRegister::factory()->create(['branch_id' => $branch->id, 'is_active' => true]);
        $user->givePermissionTo(Permission::findOrCreate('admin.pos_sessions.close'));
        $this->actingAs($user);

        return PosSession::factory()->create([
            'branch_id' => $branch->id, 'pos_register_id' => $register->id,
            'opened_by' => $user->id, 'opened_at' => now(), 'opening_float' => $opening,
        ])->load('branch');
    }

    private function movement(PosSession $session, string $amount, PosCashDirection $direction = PosCashDirection::Out): void
    {
        PosCashMovement::factory()->create([
            'branch_id' => $session->branch_id, 'pos_session_id' => $session->id,
            'pos_register_id' => $session->pos_register_id, 'currency' => $session->currency,
            'direction' => $direction, 'reason' => PosCashReason::PayOut, 'amount' => $amount,
        ]);
    }

    private function url(PosSession $session): string
    {
        return '/api/v1/pos/sessions/'.$session->id;
    }

    private function preview(PosSession $session, ?string $count = null)
    {
        return $this->postJson($this->url($session).'/closing-preview', $count === null ? [] : ['declared_cash' => $count]);
    }

    private function assertStale(PosSession $session, string $count, string $token): void
    {
        $this->putJson($this->url($session).'/close', ['declared_cash' => $count, 'review_token' => $token])->assertStatus(409);
        $this->assertSame(PosSessionStatus::Open, $session->fresh()->status);
        $this->assertNull($session->fresh()->declared_cash);
        Event::assertNotDispatched(ClosePosSession::class);
    }
}
