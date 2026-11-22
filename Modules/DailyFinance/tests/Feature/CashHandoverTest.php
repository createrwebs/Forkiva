<?php

namespace Modules\DailyFinance\Tests\Feature;

use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Modules\Branch\Models\Branch;
use Modules\DailyFinance\Models\CashHandover;
use Modules\DailyFinance\Transformers\Api\V1\CashHandoverResource;
use Modules\Pos\Enums\PosSessionStatus;
use Modules\Pos\Models\PosRegister;
use Modules\Pos\Models\PosSession;
use Modules\User\Models\Permission;
use Modules\User\Models\User;
use Tests\TestCase;

class CashHandoverTest extends TestCase
{
    use RefreshDatabase;

    protected User $sender;

    protected User $receiver;

    protected PosSession $session;

    protected function setUp(): void
    {
        parent::setUp();
        $branch = Branch::factory()->create(['currency' => 'JOD']);
        $this->sender = User::factory()->create(['branch_id' => $branch->id, 'is_active' => true]);
        $this->receiver = User::factory()->create(['branch_id' => $branch->id, 'is_active' => true]);
        foreach ([$this->sender, $this->receiver] as $user) {
            foreach (['admin.daily_finance.index', 'admin.pos_sessions.show', 'admin.daily_finance_handovers.index', 'admin.daily_finance_handovers.create', 'admin.daily_finance_handovers.receive', 'admin.daily_finance_handovers.cancel'] as $permission) {
                $user->givePermissionTo(Permission::findOrCreate($permission, 'api'));
            }
        }
        $register = PosRegister::factory()->create(['branch_id' => $branch->id]);
        $this->session = PosSession::create([
            'branch_id' => $branch->id, 'pos_register_id' => $register->id, 'opened_by' => $this->sender->id,
            'closed_by' => $this->sender->id, 'opened_at' => now()->subHour(), 'closed_at' => now(),
            'status' => PosSessionStatus::Closed, 'declared_cash' => '10.345',
            'opening_float' => '1', 'meta' => ['payment_reconciliation' => ['currency' => 'JOD'], 'cash_reconciliation' => ['expected_cash' => '10.345']],
        ]);
        $this->actingAs($this->sender);
    }

    protected function url(string $suffix = ''): string
    {
        return '/api/v1/daily-finance/sessions/'.$this->session->id.'/handovers'.$suffix;
    }

    protected function payload(array $overrides = []): array
    {
        return [...['request_key' => (string) Str::uuid(), 'amount' => '6.345', 'receiver_id' => $this->receiver->id], ...$overrides];
    }

    protected function followUpUrl(string $path = 'summary', string $query = ''): string
    {
        $this->sender->givePermissionTo(Permission::findOrCreate('admin.pos_sessions.index', 'api'));

        return '/api/v1/daily-finance/handovers/'.$path.'?branch_id='.$this->session->branch_id.$query;
    }

    public function test_follow_up_preserves_reservations_short_receipts_and_cancellation(): void
    {
        $url = $this->followUpUrl();
        $source = $this->session->fresh()->getAttributes();
        $id = $this->postJson($this->url(), $this->payload())->assertOk()->json('body.id');
        $this->getJson($url)->assertOk()->assertJsonPath('body.unhanded_sessions_count', 1)
            ->assertJsonPath('body.pending_sessions_count', 1)->assertJsonPath('body.pending_handovers_count', 1)
            ->assertJsonPath('body.balances.0.unhanded_decimal', '4.000')->assertJsonPath('body.balances.0.pending_decimal', '6.345');
        $this->actingAs($this->receiver);
        $this->postJson($this->url('/'.$id.'/receive'), ['received_amount' => '6.000', 'variance_reason' => 'Counted shortage'])->assertOk();
        $this->actingAs($this->sender);
        $this->getJson($url)->assertOk()->assertJsonPath('body.balances.0.unhanded_decimal', '4.000')
            ->assertJsonPath('body.pending_handovers_count', 0)->assertJsonPath('body.balances.0.pending_decimal', '0.000');
        $pending = $this->postJson($this->url(), $this->payload(['amount' => '4.000']))->assertOk()->json('body.id');
        $this->getJson($url)->assertOk()->assertJsonPath('body.unhanded_sessions_count', 0)->assertJsonPath('body.balances.0.pending_decimal', '4.000');
        $this->postJson($this->url('/'.$pending.'/cancel'), ['cancellation_reason' => 'Cash retained'])->assertOk();
        $this->getJson($url)->assertOk()->assertJsonPath('body.balances.0.unhanded_decimal', '4.000')
            ->assertJsonPath('body.pending_handovers_count', 0);
        $this->assertSame($source, $this->session->fresh()->getAttributes());
    }

    public function test_follow_up_surplus_consumes_cash_and_fully_received_sessions_disappear(): void
    {
        $url = $this->followUpUrl();
        $id = $this->postJson($this->url(), $this->payload())->assertOk()->json('body.id');
        $this->actingAs($this->receiver);
        $this->postJson($this->url('/'.$id.'/receive'), ['received_amount' => '10.345', 'variance_reason' => 'All counted cash received'])->assertOk();
        $this->actingAs($this->sender);
        $this->getJson($url)->assertOk()->assertJsonPath('body.unhanded_sessions_count', 0)
            ->assertJsonPath('body.pending_handovers_count', 0)->assertJsonPath('body.balances', []);
        $this->getJson($this->followUpUrl('sessions', '&kind=unhanded'))->assertOk()->assertJsonCount(0, 'body.data');
    }

    public function test_follow_up_uses_all_dates_and_keeps_historical_currencies_separate(): void
    {
        $url = $this->followUpUrl();
        $this->session->update(['closed_at' => now()->subMonths(3), 'opened_at' => now()->subMonths(3)->subHour()]);
        $usd = $this->session->replicate();
        $usd->declared_cash = '12.34';
        $usd->meta = ['payment_reconciliation' => ['currency' => 'USD']];
        $usd->save();
        $this->session->branch->update(['currency' => 'USD']);
        foreach ([['status' => PosSessionStatus::Open], ['declared_cash' => null], ['declared_cash' => '-1']] as $attributes) {
            $row = $this->session->replicate();
            $row->fill($attributes)->save();
        }
        $deleted = $this->session->replicate();
        $deleted->save();
        $deleted->delete();
        $this->getJson($url)->assertOk()->assertJsonPath('body.unhanded_sessions_count', 2)->assertJsonCount(2, 'body.balances')
            ->assertJsonPath('body.balances.0.currency', 'JOD')->assertJsonPath('body.balances.0.unhanded_decimal', '10.345')
            ->assertJsonPath('body.balances.1.currency', 'USD')->assertJsonPath('body.balances.1.unhanded_decimal', '12.34');
        $this->getJson($this->followUpUrl('sessions', '&kind=unhanded'))->assertOk()->assertJsonPath('body.pagination.total', 2)
            ->assertJsonPath('body.data.0.id', $this->session->id)->assertJsonPath('body.data.0.currency', 'JOD');
    }

    public function test_follow_up_permissions_branch_scope_and_filter_validation(): void
    {
        $url = $this->followUpUrl();
        $list = $this->followUpUrl('sessions', '&kind=unhanded');
        $foreign = Branch::factory()->create();
        $session = $this->session->replicate();
        $session->save();
        DB::table('pos_sessions')->where('id', $session->id)->update(['branch_id' => $foreign->id]);
        $this->getJson($url)->assertOk()->assertJsonPath('body.unhanded_sessions_count', 1);
        $this->getJson('/api/v1/daily-finance/handovers/summary?branch_id='.$foreign->id)->assertNotFound();
        $this->getJson('/api/v1/daily-finance/handovers/sessions?kind=pending&branch_id='.$foreign->id)->assertNotFound();
        $this->getJson($this->followUpUrl('sessions', '&kind=received'))->assertUnprocessable()->assertJsonValidationErrors('kind');
        foreach (['admin.daily_finance.index', 'admin.pos_sessions.index', 'admin.pos_sessions.show', 'admin.daily_finance_handovers.index'] as $permission) {
            $this->sender->revokePermissionTo($permission);
            $this->getJson($url)->assertForbidden();
            $this->getJson($list)->assertForbidden();
            $this->sender->givePermissionTo($permission);
        }
    }

    public function test_follow_up_sessions_are_filtered_and_paginated_beyond_summary_batch_size(): void
    {
        $url = $this->followUpUrl();
        for ($i = 0; $i < 200; $i++) {
            $session = $this->session->replicate();
            $session->save();
        }
        $this->getJson($url)->assertOk()->assertJsonPath('body.unhanded_sessions_count', 201)
            ->assertJsonPath('body.balances.0.unhanded_decimal', '2079.345');
        $list = $this->followUpUrl('sessions', '&kind=unhanded&per_page=100');
        $this->getJson($list)->assertOk()->assertJsonCount(100, 'body.data')->assertJsonPath('body.pagination.total', 201)
            ->assertJsonPath('body.pagination.last_page', 3);
        $this->getJson($list.'&page=3')->assertOk()->assertJsonCount(1, 'body.data');
        $this->getJson($this->followUpUrl('sessions', '&kind=pending'))->assertOk()->assertJsonCount(0, 'body.data');
        $this->postJson($this->url(), $this->payload())->assertOk();
        $this->getJson($this->followUpUrl('sessions', '&kind=pending'))->assertOk()->assertJsonCount(1, 'body.data')
            ->assertJsonPath('body.data.0.pending_count', 1)->assertJsonPath('body.data.0.pending.amount', 6.345);
    }

    public function test_creation_reserves_exact_cash_and_preserves_source_snapshot(): void
    {
        $snapshot = $this->session->fresh()->getAttributes();
        $data = $this->payload();
        $this->postJson($this->url(), $data)->assertOk()->assertJsonPath('body.status.id', 'pending');
        $this->postJson($this->url(), [...$data, 'request_key' => strtoupper($data['request_key'])])->assertOk();
        $this->assertDatabaseCount('daily_finance_cash_handovers', 1);
        $this->assertSame('CH-0001', CashHandover::firstOrFail()->reference_no);
        $this->getJson($this->url())->assertOk()->assertJsonPath('body.available_decimal', '4.000')
            ->assertJsonPath('body.pending.amount', 6.345)->assertJsonPath('body.received.amount', 0)
            ->assertJsonPath('body.receivers.0.id', $this->receiver->id)
            ->assertJsonPath('body.data.0.reference', 'CH-0001');
        $this->postJson($this->url(), [...$data, 'amount' => '6.344'])->assertConflict();
        $this->postJson($this->url(), $this->payload(['amount' => '4.001']))->assertUnprocessable()->assertJsonValidationErrors('amount');
        $this->assertSame($snapshot, $this->session->fresh()->getAttributes());
        $this->assertDatabaseCount('pos_cash_movements', 0);
    }

    public function test_creator_helper_and_relations_preserve_historical_identities(): void
    {
        $id = $this->postJson($this->url(), $this->payload())->assertOk()->json('body.id');
        $row = CashHandover::findOrFail($id);
        $this->assertSame($this->sender->id, $row->created_by);
        $this->assertTrue($row->createdBy->is($this->sender));
        $this->assertTrue($row->cashier->is($this->sender));
        $this->assertTrue($row->receiver->is($this->receiver));
        $this->assertTrue($row->session->is($this->session));
        $this->assertNull($row->cancelledBy);
        $this->assertSame([], array_intersect(['created_by_name', 'cashier_name', 'receiver_name'], array_keys($row->getAttributes())));
        $this->postJson($this->url('/'.$id.'/cancel'), ['cancellation_reason' => 'Retain counted cash'])->assertOk();
        $this->sender->update(['is_active' => false]);
        $this->receiver->update(['is_active' => false]);
        $this->sender->delete();
        $this->receiver->delete();
        $this->session->delete();
        $historical = CashHandover::findOrFail($id);
        $this->assertTrue($historical->createdBy->is($this->sender));
        $this->assertTrue($historical->cashier->is($this->sender));
        $this->assertTrue($historical->receiver->is($this->receiver));
        $this->assertTrue($historical->cancelledBy->is($this->sender));
        $this->assertTrue($historical->session->is($this->session));
    }

    public function test_resource_reads_actor_names_only_from_loaded_relations(): void
    {
        $id = $this->postJson($this->url(), $this->payload())->assertOk()->json('body.id');
        $this->postJson($this->url('/'.$id.'/cancel'), ['cancellation_reason' => 'Test cancelled'])->assertOk();
        $handover = CashHandover::findOrFail($id);
        $handover->preventsLazyLoading = true;
        $resource = new CashHandoverResource($handover);

        $data = $resource->toArray(request());
        $this->assertSame(['id' => $this->sender->id, 'name' => ''], $data['cashier']);
        $this->assertSame(['id' => $this->sender->id, 'name' => ''], $data['created_by']);
        $this->assertSame(['id' => $this->receiver->id, 'name' => ''], $data['receiver']);
        $this->assertSame([], $handover->getRelations());

        $handover->setRelation('cashier', $this->sender);
        $handover->setRelation('createdBy', $this->sender);
        $handover->setRelation('receiver', $this->receiver);
        $data = $resource->toArray(request());
        $this->assertSame($this->sender->name, $data['cashier']['name']);
        $this->assertSame($this->sender->name, $data['created_by']['name']);
        $this->assertSame($this->receiver->name, $data['receiver']['name']);

        foreach (['cashier', 'createdBy', 'receiver'] as $relation) {
            $handover->setRelation($relation, null);
        }
        $data = $resource->toArray(request());
        $this->assertSame('', $data['cashier']['name']);
        $this->assertSame('', $data['created_by']['name']);
        $this->assertSame('', $data['receiver']['name']);
    }

    public function test_receiver_confirms_shortage_without_releasing_missing_cash(): void
    {
        $id = $this->postJson($this->url(), $this->payload())->json('body.id');
        $url = $this->url('/'.$id.'/receive');
        $this->postJson($url, ['received_amount' => '6.000'])->assertForbidden();
        $this->actingAs($this->receiver);
        $this->postJson($url, ['received_amount' => '6.346'])->assertUnprocessable();
        $this->postJson($url, ['received_amount' => '6.000'])->assertUnprocessable()->assertJsonValidationErrors('variance_reason');
        $data = ['received_amount' => '6.000', 'variance_reason' => 'Counted shortage'];
        $this->postJson($url, $data)->assertOk()->assertJsonPath('body.status.id', 'received')->assertJsonPath('body.variance.amount', -0.345);
        $receivedAt = CashHandover::find($id)->received_at;
        $this->postJson($url, $data)->assertOk();
        $this->assertTrue($receivedAt->equalTo(CashHandover::find($id)->received_at));
        $this->postJson($url, [...$data, 'received_amount' => '6.001'])->assertConflict();
        $this->getJson($this->url())->assertOk()->assertJsonPath('body.available_decimal', '4.000')
            ->assertJsonPath('body.received.amount', 6)->assertJsonPath('body.pending.amount', 0);
        $this->actingAs($this->sender);
        $this->postJson($this->url('/'.$id.'/cancel'), ['cancellation_reason' => 'Changed mind'])->assertConflict();
    }

    public function test_receipt_surplus_consumes_only_available_unreserved_cash(): void
    {
        $id = $this->postJson($this->url(), $this->payload())->json('body.id');
        $this->actingAs($this->receiver);
        $this->postJson($this->url('/'.$id.'/receive'), ['received_amount' => '10.346', 'variance_reason' => 'Additional counted cash'])->assertUnprocessable()->assertJsonValidationErrors('received_amount');
        $this->postJson($this->url('/'.$id.'/receive'), ['received_amount' => '7.000', 'variance_reason' => 'Additional counted cash'])->assertOk()->assertJsonPath('body.variance.amount', 0.655);
        $this->getJson($this->url())->assertJsonPath('body.available_decimal', '3.345')->assertJsonPath('body.received.amount', 7);
    }

    public function test_cancellation_is_owner_only_and_releases_pending_amount(): void
    {
        $id = $this->postJson($this->url(), $this->payload())->json('body.id');
        $url = $this->url('/'.$id.'/cancel');
        $this->actingAs($this->receiver);
        $this->postJson($url, ['cancellation_reason' => 'No longer needed'])->assertForbidden();
        $this->actingAs($this->sender);
        $this->postJson($url, ['cancellation_reason' => '   '])->assertUnprocessable();
        $this->postJson($url, ['cancellation_reason' => 'No longer needed'])->assertOk()->assertJsonPath('body.status.id', 'cancelled');
        $this->postJson($url, ['cancellation_reason' => 'No longer needed'])->assertOk();
        $this->getJson($this->url())->assertOk()->assertJsonPath('body.available_decimal', '10.345');
        $this->actingAs($this->receiver);
        $this->postJson($this->url('/'.$id.'/receive'), ['received_amount' => '6.345'])->assertConflict();
    }

    public function test_permissions_branch_scope_receiver_and_counted_source_are_required(): void
    {
        $data = $this->payload();
        $this->sender->revokePermissionTo('admin.daily_finance_handovers.create');
        $this->postJson($this->url(), $data)->assertForbidden();
        $this->sender->givePermissionTo('admin.daily_finance_handovers.create');
        $this->postJson($this->url(), $this->payload(['receiver_id' => $this->sender->id]))->assertUnprocessable();
        $this->receiver->update(['branch_id' => Branch::factory()->create()->id]);
        $this->postJson($this->url(), $data)->assertUnprocessable();
        $this->receiver->update(['branch_id' => $this->session->branch_id]);
        $this->receiver->revokePermissionTo('admin.daily_finance_handovers.receive');
        $this->postJson($this->url(), $data)->assertUnprocessable();
        $this->receiver->givePermissionTo('admin.daily_finance_handovers.receive');
        $this->session->update(['status' => PosSessionStatus::Open]);
        $this->postJson($this->url(), $data)->assertUnprocessable();
        $this->session->update(['status' => PosSessionStatus::Closed, 'declared_cash' => null]);
        $this->postJson($this->url(), $data)->assertUnprocessable();
        $this->session->update(['declared_cash' => '10.345']);
        $this->sender->update(['branch_id' => Branch::factory()->create()->id]);
        $this->getJson($this->url())->assertNotFound();
        $this->postJson($this->url(), $data)->assertNotFound();
    }

    public function test_middleware_enforces_shared_and_action_permissions_on_all_handover_routes(): void
    {
        $id = $this->postJson($this->url(), $this->payload())->assertOk()->json('body.id');
        $snapshot = CashHandover::findOrFail($id)->getAttributes();
        $endpoints = [
            [$this->sender, 'GET', $this->url(), [], null],
            [$this->sender, 'POST', $this->url(), $this->payload(), 'create'],
            [$this->receiver, 'POST', $this->url('/'.$id.'/receive'), ['received_amount' => '6.345'], 'receive'],
            [$this->sender, 'POST', $this->url('/'.$id.'/cancel'), ['cancellation_reason' => 'Test cancelled'], 'cancel'],
        ];
        foreach ($endpoints as [$actor, $method, $url, $data, $action]) {
            $this->actingAs($actor);
            $permissions = ['admin.daily_finance.index', 'admin.pos_sessions.show', 'admin.daily_finance_handovers.index'];
            if ($action !== null) {
                $permissions[] = 'admin.daily_finance_handovers.'.$action;
            }
            foreach ($permissions as $permission) {
                $actor->revokePermissionTo($permission);
                $this->json($method, $url, $data)->assertForbidden();
                $actor->givePermissionTo($permission);
            }
        }
        $this->assertSame($snapshot, CashHandover::findOrFail($id)->getAttributes());
        $this->assertDatabaseCount('daily_finance_cash_handovers', 1);
    }

    public function test_currency_precision_and_server_owned_attributes_cannot_be_bypassed(): void
    {
        $this->postJson($this->url(), $this->payload(['amount' => '1.0001']))->assertUnprocessable()->assertJsonValidationErrors('amount');
        $this->postJson($this->url(), $this->payload(['amount' => '0']))->assertUnprocessable();
        $this->postJson($this->url(), $this->payload(['amount' => 1.0]))->assertUnprocessable();
        $this->postJson($this->url(), $this->payload(['amount' => '-1']))->assertUnprocessable();
        $this->postJson($this->url(), $this->payload(['amount' => '1e1']))->assertUnprocessable();
        $this->postJson($this->url(), $this->payload(['amount' => '10.345', 'currency' => 'USD', 'branch_id' => 999, 'status' => 'received', 'received_amount' => '10.345']))->assertOk()->assertJsonPath('body.amount.currency', 'JOD')->assertJsonPath('body.status.id', 'pending');
        $this->getJson($this->url())->assertJsonPath('body.available_decimal', '0.000')->assertJsonPath('body.can_create', false);
    }

    public function test_historical_currency_cannot_be_relabelled_after_branch_change(): void
    {
        $this->postJson($this->url(), $this->payload())->assertOk();
        $this->session->update(['meta' => []]);
        $this->session->branch->update(['currency' => 'USD']);
        $this->getJson($this->url())->assertOk()->assertJsonPath('body.currency', 'JOD')->assertJsonPath('body.can_create', false);
        $this->postJson($this->url(), $this->payload(['amount' => '1.000']))->assertUnprocessable();
    }

    public function test_source_permission_and_handover_resource_scope_are_enforced(): void
    {
        $id = $this->postJson($this->url(), $this->payload())->json('body.id');
        $this->sender->revokePermissionTo('admin.pos_sessions.show');
        $this->getJson($this->url())->assertForbidden();
        $this->sender->givePermissionTo('admin.pos_sessions.show');
        $this->session = PosSession::create([
            'branch_id' => $this->session->branch_id, 'pos_register_id' => $this->session->pos_register_id,
            'opened_by' => $this->sender->id, 'opened_at' => now(), 'status' => PosSessionStatus::Open, 'opening_float' => '0',
        ]);
        $this->actingAs($this->receiver);
        $this->postJson($this->url('/'.$id.'/receive'), ['received_amount' => '6.345'])->assertNotFound();
    }

    public function test_custody_actor_relations_cannot_be_detached_by_hard_deletion(): void
    {
        $id = $this->postJson($this->url(), $this->payload())->assertOk()->json('body.id');
        $before = CashHandover::findOrFail($id)->getAttributes();
        $this->assertFalse(Schema::hasColumn('daily_finance_cash_handovers', 'created_by_name'));
        $this->assertFalse(Schema::hasColumn('daily_finance_cash_handovers', 'cashier_name'));
        $this->assertFalse(Schema::hasColumn('daily_finance_cash_handovers', 'receiver_name'));
        foreach ([$this->sender, $this->receiver] as $actor) {
            try {
                DB::transaction(fn () => DB::table($actor->getTable())->where('id', $actor->id)->delete());
                $this->fail('Custody evidence must retain its actor relation.');
            } catch (QueryException $exception) {
                $this->assertSame('23000', $exception->errorInfo[0]);
            }
            $this->assertDatabaseHas($actor->getTable(), ['id' => $actor->id]);
        }
        $this->assertSame($before, CashHandover::findOrFail($id)->getAttributes());
        $this->getJson($this->url())->assertOk()->assertJsonPath('body.data.0.receiver.name', $this->receiver->name);
    }

    public function test_unassigned_reviewers_see_custody_dates_in_the_source_branch_timezone(): void
    {
        config(['app.timezone' => 'UTC']);
        $this->travelTo(now()->setDate(2026, 10, 13)->setTime(12, 0));
        $this->session->branch->update(['timezone' => 'Asia/Amman']);
        $reviewer = User::factory()->create(['branch_id' => null, 'is_active' => true]);
        $reviewer->forceFill(['branch_id' => null])->save();
        $this->assertNull($reviewer->fresh()->branch_id);
        foreach (['admin.daily_finance.index', 'admin.pos_sessions.show', 'admin.daily_finance_handovers.index', 'admin.daily_finance_handovers.create'] as $permission) {
            $reviewer->givePermissionTo(Permission::findOrCreate($permission, 'api'));
        }
        $this->actingAs($reviewer);
        $response = $this->postJson($this->url(), $this->payload())->assertOk();
        $record = CashHandover::findOrFail($response->json('body.id'));
        $createdAt = dateTimeFormat($record->created_at, timezone: 'Asia/Amman');
        $this->assertNotSame(dateTimeFormat($record->created_at), $createdAt);
        $response->assertJsonPath('body.created_at', $createdAt);
        $this->actingAs($this->receiver);
        $this->postJson($this->url('/'.$record->id.'/receive'), ['received_amount' => '6.345'])->assertOk();
        $this->actingAs($reviewer);
        $receivedAt = dateTimeFormat($record->fresh()->received_at, timezone: 'Asia/Amman');
        $this->getJson($this->url())->assertOk()
            ->assertJsonPath('body.data.0.created_at', $createdAt)
            ->assertJsonPath('body.data.0.received_at', $receivedAt);
    }

    public function test_history_uses_related_actor_names_and_is_paginated(): void
    {
        for ($i = 0; $i < 26; $i++) {
            $id = $this->postJson($this->url(), $this->payload(['amount' => '0.001']))->json('body.id');
            $this->postJson($this->url('/'.$id.'/cancel'), ['cancellation_reason' => 'Test cancelled'])->assertOk();
        }
        $this->receiver->update(['name' => 'Renamed receiver']);
        $this->sender->update(['name' => 'Renamed sender']);
        $this->getJson($this->url())->assertOk()->assertJsonCount(10, 'body.data')->assertJsonPath('body.pagination.last_page', 3)->assertJsonPath('body.data.0.receiver.name', 'Renamed receiver')
            ->assertJsonPath('body.data.0.cashier.name', 'Renamed sender')
            ->assertJsonPath('body.data.0.created_by.name', 'Renamed sender');
        $this->getJson($this->url().'?page=2')->assertOk()->assertJsonCount(10, 'body.data')->assertJsonPath('body.pagination.current_page', 2);
        $this->getJson($this->url().'?page=3')->assertOk()->assertJsonCount(6, 'body.data');
        $this->getJson($this->url().'?per_page=25')->assertOk()->assertJsonCount(25, 'body.data')->assertJsonPath('body.pagination.last_page', 2);
        $this->getJson($this->url().'?per_page=25&page=2')->assertOk()->assertJsonCount(1, 'body.data')->assertJsonPath('body.pagination.current_page', 2);
        $this->getJson($this->url().'?per_page=0')->assertOk()->assertJsonCount(1, 'body.data')->assertJsonPath('body.pagination.last_page', 26);
        $this->getJson($this->url().'?per_page=101')->assertOk()->assertJsonCount(26, 'body.data')->assertJsonPath('body.pagination.last_page', 1);
    }
}
