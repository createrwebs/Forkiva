<?php

namespace Modules\Payment\Tests\Feature;

use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Modules\Branch\Models\Branch;
use Modules\Order\Enums\OrderPaymentStatus;
use Modules\Order\Enums\OrderStatus;
use Modules\Order\Enums\OrderType;
use Modules\Order\Models\Order;
use Modules\Payment\Models\Payment;
use Modules\Pos\Models\PosRegister;
use Modules\Pos\Models\PosSession;
use Modules\User\Models\User;
use Tests\TestCase;

class PaymentSessionMigrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_populated_upgrade_preserves_payment_and_closing_history_without_inventing_attribution(): void
    {
        $migration = require module_path('Payment', 'database/migrations/2026_10_05_120000_add_pos_session_id_to_payments_table.php');
        $migration->down();
        $branch = Branch::factory()->create(['currency' => 'JOD']);
        $register = PosRegister::factory()->create(['branch_id' => $branch->id]);
        $session = PosSession::factory()->create([
            'branch_id' => $branch->id,
            'pos_register_id' => $register->id,
            'opened_by' => User::factory()->create()->id,
            'opened_at' => now(),
            'status' => 'closed',
            'declared_cash' => '12.345',
            'cash_over_short' => '0.001',
            'meta' => ['existing_evidence' => 'untouched'],
        ]);
        $order = Order::query()->create([
            'branch_id' => $branch->id,
            'pos_session_id' => $session->id,
            'status' => OrderStatus::Completed,
            'payment_status' => OrderPaymentStatus::Paid,
            'type' => OrderType::Takeaway,
            'currency' => 'JOD',
            'currency_rate' => '1',
            'subtotal' => '12.345',
            'total' => '12.345',
            'due_amount' => 0,
            'order_date' => now(),
        ]);
        $payment = $order->payments()->create([
            'branch_id' => $branch->id,
            'order_reference_no' => $order->reference_no,
            'method' => 'card',
            'type' => 'payment',
            'amount' => '12.345',
            'currency' => 'JOD',
            'currency_rate' => '1.23456789',
            'meta' => ['transaction_reference' => 'legacy'],
        ]);
        $originalPayment = $payment->fresh()->getRawOriginal();
        $originalSession = $session->fresh()->getRawOriginal();

        $migration->up();

        $upgradedPayment = $payment->fresh()->getRawOriginal();
        $this->assertNull($upgradedPayment['pos_session_id']);
        unset($upgradedPayment['pos_session_id']);
        $this->assertEquals($originalPayment, $upgradedPayment);
        $this->assertEquals($originalSession, $session->fresh()->getRawOriginal());
        $this->assertTrue(Schema::hasIndex('payments', 'payments_branch_session_received_index'));
    }

    public function test_session_link_survives_soft_deletion_and_blocks_hard_deletion_of_payment_history(): void
    {
        $branch = Branch::factory()->create();
        $session = PosSession::factory()->create([
            'branch_id' => $branch->id,
            'pos_register_id' => PosRegister::factory()->create(['branch_id' => $branch->id])->id,
            'opened_by' => User::factory()->create()->id,
            'opened_at' => now(),
        ]);
        $paymentId = DB::table('payments')->insertGetId([
            'branch_id' => $branch->id,
            'pos_session_id' => $session->id,
            'order_reference_no' => 'LEGACY',
            'method' => 'card',
            'amount' => '10',
            'currency' => 'JOD',
            'currency_rate' => '1',
        ]);
        $session->delete();
        $this->assertSame($session->id, Payment::findOrFail($paymentId)->posSession->id);

        try {
            DB::table('pos_sessions')->where('id', $session->id)->delete();
            $this->fail('A session linked to financial history must not be hard deleted.');
        } catch (QueryException $exception) {
            $this->assertStringContainsString('foreign key', strtolower($exception->getMessage()));
        }
        $this->assertDatabaseHas('payments', ['id' => $paymentId, 'pos_session_id' => $session->id]);
    }
}
