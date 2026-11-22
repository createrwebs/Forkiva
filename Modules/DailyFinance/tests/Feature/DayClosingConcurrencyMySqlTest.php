<?php

namespace Modules\DailyFinance\Tests\Feature;

use Illuminate\Support\Str;
use Modules\Branch\Models\Branch;
use Modules\Core\Contracts\FinancialExpenseSummaryReaderInterface;
use Modules\Core\Services\Accounting\NullFinancialExpenseSummaryReader;
use Modules\DailyFinance\Models\CashHandover;
use Modules\DailyFinance\Models\DayClosing;
use Modules\DailyFinance\Services\CashHandover\CashHandoverServiceInterface;
use Modules\DailyFinance\Services\DayClosing\DayClosingServiceInterface;
use Modules\Pos\Enums\PosSessionStatus;
use Modules\Pos\Models\PosRegister;
use Modules\Pos\Models\PosSession;
use Modules\User\Models\Permission;
use Modules\User\Models\User;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\Concerns\InteractsWithAccountingMySql;
use Tests\TestCase;

class DayClosingConcurrencyMySqlTest extends TestCase
{
    use InteractsWithAccountingMySql;

    private bool $committedForConcurrency = false;

    protected Branch $branch;

    protected User $user;

    protected array $payload;

    protected function setUp(): void
    {
        parent::setUp();
        $this->requireAccountingMySql(true);
        $this->rebuildAccountingMySqlSchema();
        $this->branch = Branch::factory()->create(['currency' => 'JOD', 'timezone' => 'Asia/Amman']);
        $this->user = User::factory()->create(['branch_id' => $this->branch->id]);
        foreach (['admin.daily_finance.index', 'admin.daily_finance_closings.index', 'admin.daily_finance_closings.close', 'admin.orders.index', 'admin.payments.index', 'admin.pos_cash_movements.index', 'admin.pos_sessions.index', 'admin.pos_sessions.show', 'admin.daily_finance_handovers.index'] as $permission) {
            $this->user->givePermissionTo(Permission::findOrCreate($permission, 'api'));
        }
        $this->actingAs($this->user);
        app()->instance(FinancialExpenseSummaryReaderInterface::class, new NullFinancialExpenseSummaryReader);
        $register = PosRegister::factory()->create(['branch_id' => $this->branch->id]);
        $session = PosSession::factory()->create([
            'branch_id' => $this->branch->id, 'pos_register_id' => $register->id, 'opened_by' => $this->user->id,
            'opened_at' => now()->subMinutes(30), 'closed_at' => now()->subMinutes(10), 'status' => PosSessionStatus::Closed,
            'declared_cash' => '10.345', 'meta' => ['cash_reconciliation' => ['expected_cash' => '10.345'], 'payment_reconciliation' => ['currency' => 'JOD']],
        ]);
        $before = $session->fresh()->getAttributes();
        $this->artisan('migrate', ['--path' => module_path('DailyFinance', 'database/migrations'), '--realpath' => true, '--force' => true])->assertExitCode(0);
        $this->assertSame($before, $session->fresh()->getAttributes());
        $date = now()->timezone('Asia/Amman')->toDateString();
        $review = app(DayClosingServiceInterface::class)->review(['branch_id' => $this->branch->id, 'date' => $date]);
        $this->payload = ['branch_id' => $this->branch->id, 'date' => $date, 'review_hash' => $review['review_hash'], 'notes' => null];
    }

    public function test_competing_closings_save_one_record_per_branch_day(): void
    {
        $keys = [(string) Str::uuid(), (string) Str::uuid()];
        $results = $this->runAccountingMySqlWorkers(function (int $worker) use ($keys): string {
            $this->actingAs($this->user);
            try {
                app(DayClosingServiceInterface::class)->close([...$this->payload, 'request_key' => $keys[$worker]]);

                return 'saved';
            } catch (HttpException $exception) {
                return (string) $exception->getStatusCode();
            }
        })->sort()->values()->all();
        $this->assertSame(['409', 'saved'], $results);
        $this->assertSame(1, DayClosing::count());
    }

    public function test_identical_retries_return_the_same_saved_record(): void
    {
        $key = (string) Str::uuid();
        $results = $this->runAccountingMySqlWorkers(function () use ($key): string {
            $this->actingAs($this->user);

            return (string) app(DayClosingServiceInterface::class)->close([...$this->payload, 'request_key' => $key])->id;
        });
        $this->assertCount(1, $results->unique());
        $this->assertSame(1, DayClosing::count());
    }

    public function test_custody_reservation_and_closing_follow_the_same_session_lock_order(): void
    {
        $sessionId = PosSession::firstOrFail()->id;
        $receiver = User::factory()->create(['branch_id' => $this->branch->id, 'is_active' => true]);
        foreach (['admin.daily_finance.index', 'admin.pos_sessions.show', 'admin.daily_finance_handovers.index', 'admin.daily_finance_handovers.receive'] as $permission) {
            $receiver->givePermissionTo(Permission::findOrCreate($permission, 'api'));
        }
        $keys = [(string) Str::uuid(), (string) Str::uuid()];
        $results = $this->runAccountingMySqlWorkers(function (int $worker) use ($keys, $sessionId, $receiver): string {
            $this->actingAs($this->user);
            if ($worker === 1) {
                app(CashHandoverServiceInterface::class)->create($sessionId, ['request_key' => $keys[1], 'amount' => '1.000', 'receiver_id' => $receiver->id]);

                return 'reserved';
            }
            try {
                app(DayClosingServiceInterface::class)->close([...$this->payload, 'request_key' => $keys[0]]);

                return 'closed';
            } catch (HttpException $exception) {
                return (string) $exception->getStatusCode();
            }
        });
        $this->assertSame('reserved', $results[1]);
        $this->assertContains($results[0], ['closed', '409']);
        $this->assertSame(1, CashHandover::count());
        if ($results[0] === 'closed') {
            $this->assertSame([], DayClosing::firstOrFail()->snapshot['handovers']);
        } else {
            $this->assertSame(0, DayClosing::count());
        }
    }
}
