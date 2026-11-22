<?php

namespace Modules\DailyFinance\Tests\Feature;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use LogicException;
use Modules\ActivityLog\Models\ActivityLog;
use Modules\Branch\Models\Branch;
use Modules\Core\Contracts\FinancialAccountLookupInterface;
use Modules\Core\Services\Accounting\NullFinancialAccountLookup;
use Modules\DailyFinance\Models\PaymentSettlement;
use Modules\DailyFinance\Models\PaymentSettlementItem;
use Modules\DailyFinance\Models\PaymentSettlementReceipt;
use Modules\DailyFinance\Services\PaymentSettlement\PaymentSettlementServiceInterface;
use Modules\DailyFinance\Services\PaymentSettlement\PaymentSettlementSource;
use Modules\Order\Enums\OrderPaymentStatus;
use Modules\Order\Enums\OrderStatus;
use Modules\Order\Enums\OrderType;
use Modules\Order\Models\Order;
use Modules\Payment\Enums\PaymentMethod;
use Modules\Payment\Enums\PaymentType;
use Modules\Payment\Models\Payment;
use Modules\User\Models\Permission;
use Modules\User\Models\User;
use RuntimeException;
use Spatie\Activitylog\ActivityLogStatus;
use Tests\TestCase;

class PaymentSettlementTest extends TestCase
{
    use RefreshDatabase;

    protected Branch $branch;

    protected User $user;

    protected function setUp(): void
    {
        parent::setUp();
        config(['app.timezone' => 'UTC']);
        $this->travelTo(now()->setDate(2026, 10, 12)->setTime(12, 0));
        $this->branch = Branch::factory()->create(['currency' => 'JOD', 'timezone' => 'Asia/Amman']);
        $this->user = User::factory()->create(['branch_id' => $this->branch->id, 'is_active' => true]);
        foreach (['admin.daily_finance.index', 'admin.payments.index', 'admin.daily_finance_settlements.index', 'admin.daily_finance_settlements.create', 'admin.daily_finance_settlements.receive', 'admin.daily_finance_settlements.cancel'] as $name) {
            $this->user->givePermissionTo(Permission::findOrCreate($name, 'api'));
        }
        $this->actingAs($this->user);
        app()->instance(FinancialAccountLookupInterface::class, new NullFinancialAccountLookup);
    }

    protected function collection(array $attributes = []): Payment
    {
        $createdAt = $attributes['created_at'] ?? null;
        unset($attributes['created_at']);
        $order = Order::create([
            'branch_id' => $this->branch->id,
            'status' => OrderStatus::Completed,
            'type' => OrderType::Takeaway,
            'payment_status' => OrderPaymentStatus::Paid,
            'currency' => 'JOD',
            'currency_rate' => 1,
            'subtotal' => '10.345',
            'total' => '10.345',
            'due_amount' => '0',
            'order_date' => '2026-10-12',
        ]);

        $payment = Payment::create([
            'branch_id' => $this->branch->id,
            'order_id' => $order->id,
            'order_reference_no' => $order->reference_no,
            'method' => PaymentMethod::Card,
            'type' => PaymentType::Payment,
            'currency' => 'JOD',
            'currency_rate' => 1,
            'amount' => '10.345',
            'received_at' => '2026-10-12 08:00:00',
            ...$attributes,
        ]);
        if ($createdAt) {
            $payment->forceFill(['created_at' => $createdAt])->save();
        }

        return $payment->fresh();
    }

    protected function payload(Payment $payment, array $extra = []): array
    {
        return [
            'branch_id' => $this->branch->id,
            'method' => 'card',
            'payment_ids' => [$payment->id],
            'source_hashes' => [$payment->id => PaymentSettlementSource::hash($payment)],
            'fees' => '0.045',
            'destination_name' => 'Main bank',
            'reference' => 'GATEWAY-001',
            'request_key' => (string) Str::uuid(),
            ...$extra,
        ];
    }

    protected function receipt(array $extra = []): array
    {
        return [
            'branch_id' => $this->branch->id,
            'amount' => '10.300',
            'received_date' => '2026-10-12',
            'reference' => 'BANK-001',
            'is_final' => true,
            'request_key' => (string) Str::uuid(),
            ...$extra,
        ];
    }

    protected function url(string $suffix = ''): string
    {
        return '/api/v1/daily-finance/settlements'.$suffix;
    }

    public function test_bank_credits_have_branch_relations_and_scopes(): void
    {
        $id = $this->postJson($this->url(), $this->payload($this->collection()))->assertOk()->json('body.id');
        $this->postJson($this->url("/$id/receive"), $this->receipt())->assertOk();
        $record = PaymentSettlementReceipt::firstOrFail();
        $this->assertSame($this->branch->id, $record->branch_id);
        $this->assertTrue($record->branch->is($this->branch));
        $this->assertSame(1, PaymentSettlementReceipt::whereBranch($this->branch->id)->count());

        $originalBranch = $this->branch;
        $this->branch = Branch::factory()->create(['currency' => 'JOD', 'timezone' => 'Asia/Amman']);
        $otherUser = User::factory()->create(['branch_id' => $this->branch->id, 'is_active' => true]);
        $otherUser->forceFill(['branch_id' => $this->branch->id])->save();
        $otherUser->givePermissionTo($this->user->getAllPermissions());
        $this->actingAs($otherUser);
        $otherId = $this->postJson($this->url(), $this->payload($this->collection()))->assertOk()->json('body.id');
        $this->postJson($this->url("/$otherId/receive"), $this->receipt())->assertOk();
        $this->assertSame(1, PaymentSettlementReceipt::count());
        $this->assertSame(0, PaymentSettlementReceipt::whereBranch($originalBranch->id)->count());
        $this->assertSame(2, PaymentSettlementReceipt::withOutGlobalBranchPermission()->count());

        $this->actingAs($this->user);
        $this->assertSame([$id], PaymentSettlementReceipt::query()->pluck('payment_settlement_id')->all());
    }

    public function test_settlement_activity_records_actors_and_changes_without_duplicate_replay_logs(): void
    {
        config(['activitylog.enabled' => true]);
        app(ActivityLogStatus::class)->enable();
        $payload = $this->payload($this->collection());
        $id = $this->postJson($this->url(), $payload)->assertOk()->json('body.id');
        $this->postJson($this->url(), $payload)->assertOk();
        $credit = $this->receipt(['amount' => '5.000', 'is_final' => false]);
        $this->postJson($this->url("/$id/receive"), $credit)->assertOk();
        $this->postJson($this->url("/$id/receive"), $credit)->assertOk();
        $this->postJson($this->url("/$id/receive"), $this->receipt(['amount' => '5.300', 'reference' => 'BANK-002']))->assertOk();

        $batchLogs = ActivityLog::query()->where('subject_type', PaymentSettlement::class)->where('subject_id', $id)->get();
        $this->assertSame(['created', 'updated', 'updated'], $batchLogs->pluck('event')->all());
        $this->assertSame('pending', $batchLogs[1]->properties['old']['status']);
        $this->assertSame('partial', $batchLogs[1]->properties['attributes']['status']);
        $this->assertSame('settled', $batchLogs[2]->properties['attributes']['status']);
        $receiptLogs = ActivityLog::query()->where('subject_type', PaymentSettlementReceipt::class)->get();
        $this->assertCount(2, $receiptLogs);
        $this->assertSame('payment_settlement_receipts.created', $receiptLogs[0]->log_name);
        foreach ($batchLogs->concat($receiptLogs) as $log) {
            $this->assertSame($this->user->id, $log->causer_id);
            $this->assertSame(User::class, $log->causer_type);
            $this->assertSame($this->branch->id, $log->properties['attributes']['branch_id']);
            $this->assertNotSame($log->properties['trans_params']['resource'], __($log->properties['trans_params']['resource']));
        }

        $cancelId = $this->postJson($this->url(), $this->payload($this->collection()))->assertOk()->json('body.id');
        $cancel = ['branch_id' => $this->branch->id, 'cancellation_reason' => 'Wrong gateway batch'];
        $this->postJson($this->url("/$cancelId/cancel"), $cancel)->assertOk();
        $this->postJson($this->url("/$cancelId/cancel"), $cancel)->assertOk();
        $cancelLog = ActivityLog::query()->where('subject_type', PaymentSettlement::class)->where('subject_id', $cancelId)->where('event', 'updated')->sole();
        $this->assertSame('cancelled', $cancelLog->properties['attributes']['status']);
        $this->assertSame($this->user->id, $cancelLog->properties['attributes']['cancelled_by']);
        $this->assertSame('Wrong gateway batch', $cancelLog->properties['attributes']['cancellation_reason']);
        $this->assertSame(0, ActivityLog::query()->where('subject_type', PaymentSettlementItem::class)->count());
    }

    public function test_unassigned_actors_preserve_the_batch_branch_on_receipts(): void
    {
        $actor = User::factory()->create(['is_active' => true]);
        $actor->forceFill(['branch_id' => null])->save();
        $actor->givePermissionTo($this->user->getAllPermissions());
        $this->actingAs($actor);
        $id = $this->postJson($this->url(), $this->payload($this->collection()))->assertOk()->json('body.id');
        $this->postJson($this->url("/$id/receive"), $this->receipt())->assertOk();
        $record = PaymentSettlementReceipt::firstOrFail();
        $this->assertSame($this->branch->id, $record->branch_id);
        $this->assertTrue($record->branch->is($this->branch));
        $this->assertTrue(PaymentSettlementItem::firstOrFail()->settlement->branch->is($this->branch));
    }

    public function test_failed_settlement_transactions_roll_back_their_activity(): void
    {
        config(['activitylog.enabled' => true]);
        app(ActivityLogStatus::class)->enable();
        $payload = $this->payload($this->collection());
        try {
            DB::transaction(function () use ($payload): void {
                app(PaymentSettlementServiceInterface::class)->create($payload);
                $this->assertSame(1, ActivityLog::query()->where('subject_type', PaymentSettlement::class)->count());
                throw new RuntimeException('Abort the settlement transaction.');
            });
            $this->fail('The transaction must fail.');
        } catch (RuntimeException $exception) {
            $this->assertSame('Abort the settlement transaction.', $exception->getMessage());
        }
        $this->assertDatabaseCount('daily_finance_payment_settlements', 0);
        $this->assertSame(0, ActivityLog::query()->where('subject_type', PaymentSettlement::class)->count());
    }

    public function test_exact_batch_and_partial_credits_leave_source_payments_unchanged(): void
    {
        $payment = $this->collection();
        $before = $payment->fresh()->getAttributes();
        $payload = $this->payload($payment);
        $id = $this->postJson($this->url(), $payload)->assertOk()
            ->assertJsonPath('body.reference', 'PS-0001')
            ->assertJsonPath('body.batch_reference', 'GATEWAY-001')
            ->assertJsonPath('body.expected.amount', 10.3)
            ->assertJsonPath('body.status.id', 'pending')->json('body.id');
        $this->postJson($this->url(), $payload)->assertOk()->assertJsonPath('body.id', $id)
            ->assertJsonPath('body.reference', PaymentSettlement::findOrFail($id)->reference_no);
        $first = $this->receipt(['amount' => '5.125', 'is_final' => false]);
        $this->postJson($this->url("/$id/receive"), $first)->assertOk()
            ->assertJsonPath('body.status.id', 'partial')->assertJsonPath('body.remaining.amount', 5.175)
            ->assertJsonPath('body.variance', null);
        $this->postJson($this->url("/$id/receive"), $first)->assertOk()->assertJsonCount(1, 'body.receipts');
        $this->postJson($this->url("/$id/receive"), $this->receipt(['amount' => '5.175', 'reference' => 'BANK-002']))->assertOk()
            ->assertJsonPath('body.status.id', 'settled')->assertJsonPath('body.variance.amount', 0)
            ->assertJsonCount(2, 'body.receipts');
        $this->assertSame($before, $payment->fresh()->getAttributes());
        $this->assertDatabaseCount('daily_finance_payment_settlements', 1);
        $this->assertDatabaseCount('daily_finance_payment_settlement_receipts', 2);
    }

    public function test_creation_and_receipt_do_not_discard_unfillable_attributes(): void
    {
        $payload = $this->payload($this->collection());
        $preventDiscarding = Model::preventsSilentlyDiscardingAttributes();
        Model::preventSilentlyDiscardingAttributes();

        try {
            $id = $this->postJson($this->url(), $payload)->assertOk()->json('body.id');
            $this->postJson($this->url("/$id/receive"), $this->receipt())->assertOk()
                ->assertJsonPath('body.status.id', 'settled');
            $this->assertSame($this->branch->id, PaymentSettlementItem::firstOrFail()->settlement->branch_id);
        } finally {
            Model::preventSilentlyDiscardingAttributes($preventDiscarding);
        }
    }

    public function test_duplicate_sources_and_changed_idempotent_replays_are_rejected(): void
    {
        $payment = $this->collection();
        $payload = $this->payload($payment);
        $this->postJson($this->url(), $payload)->assertOk();
        $this->postJson($this->url(), $this->payload($payment))->assertUnprocessable()->assertJsonValidationErrors('payment_ids');
        $this->postJson($this->url(), [...$payload, 'fees' => '0.050'])->assertConflict();
        $this->postJson($this->url(), $this->payload($payment, ['payment_ids' => [$payment->id, $payment->id]]))->assertUnprocessable();
    }

    public function test_final_shortages_and_surpluses_require_an_explanation(): void
    {
        foreach (['9.300', '11.300'] as $index => $amount) {
            $id = $this->postJson($this->url(), $this->payload($this->collection()))->assertOk()->json('body.id');
            $payload = $this->receipt(['amount' => $amount, 'reference' => 'BANK-'.$index]);
            $this->postJson($this->url("/$id/receive"), $payload)->assertUnprocessable()->assertJsonValidationErrors('variance_reason');
            $this->postJson($this->url("/$id/receive"), [...$payload, 'variance_reason' => 'Gateway statement difference'])->assertOk()
                ->assertJsonPath('body.status.id', 'settled');
            $this->postJson($this->url("/$id/receive"), $this->receipt())->assertConflict();
        }
    }

    public function test_bank_reference_cannot_be_used_for_two_batches_on_the_same_account(): void
    {
        $first = $this->postJson($this->url(), $this->payload($this->collection()))->assertOk()->json('body.id');
        $second = $this->postJson($this->url(), $this->payload($this->collection()))->assertOk()->json('body.id');
        $this->postJson($this->url("/$first/receive"), $this->receipt())->assertOk();
        $this->postJson($this->url("/$second/receive"), $this->receipt(['reference' => 'bank-001']))->assertUnprocessable()->assertJsonValidationErrors('reference');
    }

    public function test_cancellation_releases_the_claim_but_retains_source_evidence(): void
    {
        $payment = $this->collection();
        $id = $this->postJson($this->url(), $this->payload($payment))->assertOk()->json('body.id');
        $cancel = ['branch_id' => $this->branch->id, 'cancellation_reason' => 'Selected wrong batch'];
        $this->postJson($this->url("/$id/cancel"), $cancel)->assertOk()->assertJsonPath('body.status.id', 'cancelled');
        $this->postJson($this->url("/$id/cancel"), $cancel)->assertOk();
        $this->assertSame($payment->id, PaymentSettlementItem::first()->payment_id);
        $this->assertNull(PaymentSettlementItem::first()->active_payment_id);
        $this->postJson($this->url(), $this->payload($payment))->assertOk();
        $this->assertDatabaseCount('daily_finance_payment_settlement_items', 2);
        $this->postJson($this->url("/$id/receive"), $this->receipt())->assertConflict();
    }

    public function test_partial_credits_cannot_be_cancelled_and_receipts_are_immutable(): void
    {
        $id = $this->postJson($this->url(), $this->payload($this->collection()))->assertOk()->json('body.id');
        $this->postJson($this->url("/$id/receive"), $this->receipt(['amount' => '2.000', 'is_final' => false]))->assertOk();
        $this->postJson($this->url("/$id/cancel"), ['branch_id' => $this->branch->id, 'cancellation_reason' => 'Wrong batch'])->assertConflict();
        $this->expectException(LogicException::class);
        PaymentSettlementReceipt::firstOrFail()->update(['amount' => '1.000']);
    }

    public function test_sources_methods_precision_and_fees_are_validated(): void
    {
        foreach ([['method' => PaymentMethod::Cash], ['type' => PaymentType::Refund], ['voided_at' => now()], ['currency' => 'USD']] as $attributes) {
            $this->postJson($this->url(), $this->payload($this->collection($attributes)))->assertUnprocessable();
        }
        $payment = $this->collection();
        foreach (['10.345', '10.346', '0.0001'] as $fees) {
            $this->postJson($this->url(), $this->payload($payment, ['fees' => $fees]))->assertUnprocessable()->assertJsonValidationErrors('fees');
        }
        $this->postJson($this->url(), $this->payload($payment, ['method' => 'cash']))->assertUnprocessable();
        $this->postJson($this->url(), $this->payload($payment, ['destination_name' => ' ']))->assertUnprocessable();
    }

    public function test_collection_calendar_boundaries_and_exclusive_reservations_are_reflected_in_candidates(): void
    {
        $included = $this->collection(['received_at' => '2026-10-11 21:00:00']);
        $legacy = $this->collection(['received_at' => null, 'created_at' => '2026-10-11 21:00:00']);
        $this->collection(['received_at' => '2026-10-11 20:59:59']);
        $query = '?'.http_build_query(['branch_id' => $this->branch->id, 'method' => 'card', 'from' => '2026-10-12', 'to' => '2026-10-12']);
        $this->getJson($this->url('/candidates').$query)->assertOk()->assertJsonPath('body.pagination.total', 2);
        $this->postJson($this->url(), $this->payload($included))->assertOk();
        $this->getJson($this->url('/candidates').$query)->assertOk()->assertJsonPath('body.pagination.total', 1)->assertJsonPath('body.data.0.id', $legacy->id);
    }

    public function test_future_and_pre_collection_bank_credit_dates_are_rejected(): void
    {
        $id = $this->postJson($this->url(), $this->payload($this->collection()))->assertOk()->json('body.id');
        foreach (['2026-10-11', '2026-10-13'] as $date) {
            $this->postJson($this->url("/$id/receive"), $this->receipt(['received_date' => $date]))->assertUnprocessable()->assertJsonValidationErrors('received_date');
        }
    }

    public function test_branch_and_action_permissions_protect_every_path(): void
    {
        $id = $this->postJson($this->url(), $this->payload($this->collection()))->assertOk()->json('body.id');
        $other = Branch::factory()->create();
        $this->getJson($this->url("/$id?branch_id=$other->id"))->assertNotFound();
        foreach (['create', 'receive', 'cancel'] as $action) {
            $this->user->revokePermissionTo('admin.daily_finance_settlements.'.$action);
            $url = match ($action) {
                'create' => '', 'receive' => "/$id/receive", default => "/$id/cancel"
            };
            $this->postJson($this->url($url), $this->receipt())->assertForbidden();
            $this->user->givePermissionTo('admin.daily_finance_settlements.'.$action);
        }
        $this->user->revokePermissionTo('admin.payments.index');
        $this->getJson($this->url('?branch_id='.$this->branch->id))->assertForbidden();
        $this->getJson($this->url('/meta?branch_id='.$this->branch->id))->assertForbidden();
    }

    public function test_accounting_unavailable_path_accepts_manual_destination_and_rejects_forged_account_keys(): void
    {
        $this->getJson($this->url('/meta?branch_id='.$this->branch->id))->assertOk()
            ->assertJsonPath('body.linked_accounts_available', false)->assertJsonCount(0, 'body.accounts');
        $this->user->givePermissionTo(Permission::findOrCreate('admin.accounting_financial_accounts.index', 'api'));
        $this->postJson($this->url(), $this->payload($this->collection(), ['account_key' => 'financial_account:999']))->assertUnprocessable()->assertJsonValidationErrors('account_key');
        $this->postJson($this->url(), $this->payload($this->collection()))->assertOk()->assertJsonPath('body.destination_name', 'Main bank');
    }

    public function test_foreign_branch_sources_and_mutations_are_rejected_without_writes(): void
    {
        $payment = $this->collection();
        $id = $this->postJson($this->url(), $this->payload($payment))->assertOk()->json('body.id');
        $other = Branch::factory()->create(['currency' => 'JOD']);
        $foreign = $this->collection();
        $foreign->update(['branch_id' => $other->id]);
        $this->postJson($this->url(), $this->payload($foreign))->assertUnprocessable()
            ->assertJsonValidationErrors('payment_ids');

        $this->user->update(['branch_id' => $other->id]);
        $this->actingAs($this->user->fresh());
        $this->getJson($this->url("/$id?branch_id=$other->id"))->assertNotFound();
        $this->postJson($this->url("/$id/receive"), $this->receipt(['branch_id' => $other->id]))->assertNotFound();
        $this->postJson($this->url("/$id/cancel"), [
            'branch_id' => $other->id, 'cancellation_reason' => 'Wrong branch',
        ])->assertNotFound();
        $this->assertDatabaseCount('daily_finance_payment_settlements', 1);
        $this->assertDatabaseCount('daily_finance_payment_settlement_receipts', 0);
        $this->assertDatabaseHas('daily_finance_payment_settlements', ['id' => $id, 'status' => 'pending']);
    }

    public function test_foundation_permissions_protect_reads_and_writes(): void
    {
        $payload = $this->payload($this->collection());
        $id = $this->postJson($this->url(), $payload)->assertOk()->json('body.id');
        foreach (['admin.daily_finance.index', 'admin.payments.index', 'admin.daily_finance_settlements.index'] as $permission) {
            $this->user->revokePermissionTo($permission);
            foreach (['', '/meta', '/summary', '/candidates', "/$id"] as $path) {
                $this->getJson($this->url($path).'?branch_id='.$this->branch->id)->assertForbidden();
            }
            $this->postJson($this->url(), $payload)->assertForbidden();
            $this->postJson($this->url("/$id/receive"), $this->receipt())->assertForbidden();
            $this->postJson($this->url("/$id/cancel"), [
                'branch_id' => $this->branch->id, 'cancellation_reason' => 'Wrong batch',
            ])->assertForbidden();
            $this->user->givePermissionTo($permission);
        }
        $this->assertDatabaseCount('daily_finance_payment_settlement_receipts', 0);
    }

    public function test_ineligible_sources_and_transaction_search_preserve_candidate_scope(): void
    {
        $valid = $this->collection(['transaction_id' => 'GATEWAY-SEARCH']);
        $other = Branch::factory()->create(['currency' => 'JOD']);
        $foreign = $this->collection(['transaction_id' => 'GATEWAY-SEARCH']);
        $foreign->update(['branch_id' => $other->id]);
        $invalid = [
            $foreign,
            $this->collection(['received_at' => now()->addDay()]),
            $this->collection(['amount' => '0']),
            $this->collection(['method' => PaymentMethod::GiftCard]),
            $this->collection(['method' => PaymentMethod::ReservationDeposit]),
            $this->collection(),
        ];
        $invalid[array_key_last($invalid)]->delete();
        foreach ($invalid as $payment) {
            $this->postJson($this->url(), $this->payload($payment))->assertUnprocessable()
                ->assertJsonValidationErrors('payment_ids');
        }
        $query = '?'.http_build_query([
            'branch_id' => $this->branch->id, 'method' => 'card',
            'from' => '2026-10-12', 'to' => '2026-10-13', 'search' => 'GATEWAY-SEARCH',
        ]);
        $this->getJson($this->url('/candidates').$query)->assertOk()
            ->assertJsonPath('body.pagination.total', 1)->assertJsonPath('body.data.0.id', $valid->id);
        $this->assertDatabaseCount('daily_finance_payment_settlements', 0);
    }

    public function test_changed_receipt_replay_is_rejected_even_after_settlement_completes(): void
    {
        $id = $this->postJson($this->url(), $this->payload($this->collection()))->assertOk()->json('body.id');
        $payload = $this->receipt();
        $this->postJson($this->url("/$id/receive"), $payload)->assertOk();
        $this->postJson($this->url("/$id/receive"), [...$payload, 'amount' => '10.200'])->assertConflict();
        $this->assertDatabaseCount('daily_finance_payment_settlement_receipts', 1);
    }

    public function test_changed_collections_require_a_fresh_review_and_snapshots_cannot_be_rewritten(): void
    {
        $payment = $this->collection();
        $payload = $this->payload($payment);
        $payment->update(['amount' => '11.345']);
        $this->postJson($this->url(), $payload)->assertConflict();
        $this->assertDatabaseCount('daily_finance_payment_settlements', 0);
        $this->postJson($this->url(), $this->payload($payment->fresh()))->assertOk();
        $this->expectException(LogicException::class);
        PaymentSettlementItem::firstOrFail()->update(['amount' => '1.000']);
    }

    public function test_over_precise_sources_are_visible_but_cannot_be_selected(): void
    {
        $payment = $this->collection(['amount' => '10.3451']);
        $query = '?'.http_build_query(['branch_id' => $this->branch->id, 'method' => 'card', 'from' => '2026-10-12', 'to' => '2026-10-12']);
        $this->getJson($this->url('/candidates').$query)->assertOk()->assertJsonPath('body.data.0.selectable', false);
        $this->postJson($this->url(), $this->payload($payment))->assertUnprocessable()->assertJsonValidationErrors('payment_ids');
        $this->assertDatabaseCount('daily_finance_payment_settlements', 0);
    }

    public function test_optional_bank_lookup_filters_and_revalidates_account_context(): void
    {
        $branchId = $this->branch->id;
        app()->instance(FinancialAccountLookupInterface::class, new class($branchId) implements FinancialAccountLookupInterface
        {
            public function __construct(private int $branchId) {}

            public function available(): bool
            {
                return true;
            }

            public function list(int $branchId, ?string $currency = null): array
            {
                return [
                    ['key' => 'bank:1', 'name' => 'Branch bank', 'type' => ['id' => 'bank']],
                    ['key' => 'cash:1', 'name' => 'Cash drawer', 'type' => ['id' => 'cash']],
                    ['key' => 'other:1', 'name' => 'Other branch', 'type' => ['id' => 'bank']],
                ];
            }

            public function resolve(string $key, int $branchId, string $currency): ?array
            {
                return $key === 'bank:1' && $branchId === $this->branchId && $currency === 'JOD'
                    ? ['key' => $key, 'name' => 'Branch bank', 'type' => 'bank'] : null;
            }
        });
        $this->getJson($this->url('/meta?branch_id='.$branchId))->assertOk()->assertJsonCount(0, 'body.accounts');
        $this->postJson($this->url(), $this->payload($this->collection(), ['account_key' => 'bank:1']))->assertForbidden();
        $this->user->givePermissionTo(Permission::findOrCreate('admin.accounting_financial_accounts.index', 'api'));
        $this->getJson($this->url('/meta?branch_id='.$branchId))->assertOk()->assertJsonCount(1, 'body.accounts')->assertJsonPath('body.accounts.0.name', 'Branch bank');
        $this->postJson($this->url(), $this->payload($this->collection(), ['account_key' => 'other:1']))->assertUnprocessable();
        $this->postJson($this->url(), $this->payload($this->collection(), ['account_key' => 'bank:1']))->assertOk()->assertJsonPath('body.destination_name', 'Branch bank');
    }

    public function test_history_is_paginated_and_preserves_the_original_currency_on_replay(): void
    {
        $payload = null;
        for ($index = 0; $index < 12; $index++) {
            $payload = $this->payload($this->collection());
            $this->postJson($this->url(), $payload)->assertOk();
        }
        $this->getJson($this->url('?branch_id='.$this->branch->id.'&page=2'))->assertOk()
            ->assertJsonPath('body.pagination.total', 12)->assertJsonCount(2, 'body.data');
        $this->branch->update(['currency' => 'USD']);
        $this->postJson($this->url(), $payload)->assertOk()->assertJsonPath('body.currency', 'JOD');
    }

    public function test_current_summary_separates_currencies_and_excludes_terminal_and_foreign_branch_batches(): void
    {
        $pending = $this->postJson($this->url(), $this->payload($this->collection()))->assertOk()->json('body.id');
        $partial = $this->postJson($this->url(), $this->payload($this->collection()))->assertOk()->json('body.id');
        $this->postJson($this->url("/$partial/receive"), $this->receipt(['amount' => '5.125', 'is_final' => false]))->assertOk();
        $settled = $this->postJson($this->url(), $this->payload($this->collection()))->assertOk()->json('body.id');
        $this->postJson($this->url("/$settled/receive"), $this->receipt(['reference' => 'BANK-002']))->assertOk();
        $cancelled = $this->postJson($this->url(), $this->payload($this->collection()))->assertOk()->json('body.id');
        $this->postJson($this->url("/$cancelled/cancel"), ['branch_id' => $this->branch->id, 'cancellation_reason' => 'Wrong batch'])->assertOk();
        $foreign = $this->postJson($this->url(), $this->payload($this->collection()))->assertOk()->json('body.id');
        $other = Branch::factory()->create();
        DB::table('daily_finance_payment_settlements')->where('id', $foreign)->update(['branch_id' => $other->id]);
        DB::table('daily_finance_payment_settlements')->where('id', $pending)->update(['created_at' => '2025-01-01 09:00:00']);
        $this->branch->update(['currency' => 'USD']);
        $this->postJson($this->url(), $this->payload($this->collection(['currency' => 'USD', 'amount' => '10.2000']), ['fees' => '0.20']))->assertOk();

        $this->getJson($this->url('/summary').'?branch_id='.$this->branch->id)->assertOk()
            ->assertJsonPath('body.open_count', 3)
            ->assertJsonPath('body.pending_count', 2)
            ->assertJsonPath('body.partial_count', 1)
            ->assertJsonCount(2, 'body.balances')
            ->assertJsonPath('body.balances.0.currency', 'JOD')
            ->assertJsonPath('body.balances.0.remaining_decimal', '15.475')
            ->assertJsonPath('body.balances.1.currency', 'USD')
            ->assertJsonPath('body.balances.1.remaining_decimal', '10.00');
        $this->getJson($this->url('?branch_id='.$this->branch->id.'&status=outstanding'))->assertOk()
            ->assertJsonPath('body.pagination.total', 3);
        $this->getJson($this->url('/summary').'?branch_id='.$other->id)->assertNotFound();
    }

    public function test_summary_is_not_limited_to_a_history_page_or_one_read_batch(): void
    {
        $this->postJson($this->url(), $this->payload($this->collection()))->assertOk();
        $template = PaymentSettlement::firstOrFail()->getAttributes();
        unset($template['id']);
        $records = [];
        for ($index = 0; $index < 200; $index++) {
            $records[] = [...$template, 'reference' => 'PS-TEST-'.$index, 'reference_no' => 'PS-'.str_pad((string) ($index + 2), 4, '0', STR_PAD_LEFT), 'request_key' => (string) Str::uuid()];
        }
        DB::table('daily_finance_payment_settlements')->insert($records);
        $this->getJson($this->url('/summary').'?branch_id='.$this->branch->id)->assertOk()
            ->assertJsonPath('body.open_count', 201)
            ->assertJsonPath('body.balances.0.remaining_decimal', '2070.300');
    }

    public function test_empty_summary_is_distinct_from_restricted_access(): void
    {
        $url = $this->url('/summary').'?branch_id='.$this->branch->id;
        $this->getJson($url)->assertOk()->assertJsonPath('body.open_count', 0)->assertJsonCount(0, 'body.balances');
        $this->user->revokePermissionTo('admin.daily_finance_settlements.index');
        $this->getJson($url)->assertForbidden();
    }
}
