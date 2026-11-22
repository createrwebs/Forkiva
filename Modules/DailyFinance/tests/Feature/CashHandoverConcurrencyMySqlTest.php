<?php

namespace Modules\DailyFinance\Tests\Feature;

use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Modules\Branch\Models\Branch;
use Modules\DailyFinance\Models\CashHandover;
use Modules\DailyFinance\Services\CashHandover\CashHandoverServiceInterface;
use Modules\Pos\Enums\PosSessionStatus;
use Modules\Pos\Models\PosRegister;
use Modules\Pos\Models\PosSession;
use Modules\User\Models\Permission;
use Modules\User\Models\User;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\Concerns\InteractsWithAccountingMySql;
use Tests\TestCase;

class CashHandoverConcurrencyMySqlTest extends TestCase
{
    use InteractsWithAccountingMySql;

    private bool $committedForConcurrency = false;

    protected User $sender;

    protected User $receiver;

    protected PosSession $session;

    protected function setUp(): void
    {
        parent::setUp();
        $this->requireAccountingMySql(true);
        $this->rebuildAccountingMySqlSchema();
        $branch = Branch::factory()->create(['currency' => 'JOD']);
        $this->sender = User::factory()->create(['branch_id' => $branch->id, 'is_active' => true]);
        $this->receiver = User::factory()->create(['branch_id' => $branch->id, 'is_active' => true]);
        foreach ([$this->sender, $this->receiver] as $user) {
            foreach (['admin.daily_finance.index', 'admin.pos_sessions.show', 'admin.daily_finance_handovers.index', 'admin.daily_finance_handovers.create', 'admin.daily_finance_handovers.receive', 'admin.daily_finance_handovers.cancel'] as $permission) {
                $user->givePermissionTo(Permission::findOrCreate($permission, 'api'));
            }
        }
        $this->actingAs($this->sender);
        $register = PosRegister::factory()->create(['branch_id' => $branch->id]);
        $this->session = PosSession::create([
            'branch_id' => $branch->id, 'pos_register_id' => $register->id, 'opened_by' => $this->sender->id,
            'closed_by' => $this->sender->id, 'opened_at' => now()->subHour(), 'closed_at' => now(),
            'status' => PosSessionStatus::Closed, 'declared_cash' => '10.345', 'opening_float' => '1',
            'meta' => ['payment_reconciliation' => ['currency' => 'JOD']],
        ]);
        // Run the additive upgrade against a populated POS source, not just an empty installation.
        $snapshot = $this->session->fresh()->getAttributes();
        $this->artisan('migrate', ['--path' => module_path('DailyFinance', 'database/migrations'), '--realpath' => true, '--force' => true])->assertExitCode(0);
        $this->assertSame($snapshot, $this->session->fresh()->getAttributes());
    }

    public function test_two_requests_cannot_over_reserve_counted_cash(): void
    {
        $keys = [(string) Str::uuid(), (string) Str::uuid()];
        $results = $this->runAccountingMySqlWorkers(function (int $worker) use ($keys): string {
            $this->actingAs($this->sender);
            try {
                app(CashHandoverServiceInterface::class)->create($this->session->id, ['request_key' => $keys[$worker], 'amount' => '7.000', 'receiver_id' => $this->receiver->id]);

                return 'saved';
            } catch (ValidationException) {
                return 'unavailable';
            }
        })->sort()->values()->all();
        $this->assertSame(['saved', 'unavailable'], $results);
        $this->assertSame(1, CashHandover::count());
        $this->assertSame('3.345', app(CashHandoverServiceInterface::class)->summary($this->session->id)['available_decimal']);
    }

    public function test_identical_request_keys_replay_one_record_under_concurrency(): void
    {
        $key = (string) Str::uuid();
        $results = $this->runAccountingMySqlWorkers(function () use ($key): string {
            $this->actingAs($this->sender);

            return (string) app(CashHandoverServiceInterface::class)->create($this->session->id, ['request_key' => $key, 'amount' => '7.000', 'receiver_id' => $this->receiver->id])->id;
        });
        $this->assertCount(1, $results->unique());
        $this->assertSame(1, CashHandover::count());
    }

    public function test_receipt_and_cancellation_cannot_both_commit(): void
    {
        $id = app(CashHandoverServiceInterface::class)->create($this->session->id, ['request_key' => (string) Str::uuid(), 'amount' => '7.000', 'receiver_id' => $this->receiver->id])->id;
        $results = $this->runAccountingMySqlWorkers(function (int $worker) use ($id): string {
            $this->actingAs($worker === 0 ? $this->sender : $this->receiver);
            try {
                $service = app(CashHandoverServiceInterface::class);
                if ($worker === 0) {
                    $service->cancel($this->session->id, $id, ['cancellation_reason' => 'Cancel pending custody']);
                } else {
                    $service->receive($this->session->id, $id, ['received_amount' => '7.000']);
                }

                return 'saved';
            } catch (HttpException $exception) {
                return (string) $exception->getStatusCode();
            }
        })->sort()->values()->all();
        $this->assertSame(['409', 'saved'], $results);
        $row = CashHandover::findOrFail($id);
        $this->assertFalse($row->received_at !== null && $row->cancelled_at !== null);
    }
}
