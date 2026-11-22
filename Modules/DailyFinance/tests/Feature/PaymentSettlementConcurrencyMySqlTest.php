<?php

namespace Modules\DailyFinance\Tests\Feature;

use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
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
use Modules\User\Models\User;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\Concerns\InteractsWithAccountingMySql;
use Tests\TestCase;

class PaymentSettlementConcurrencyMySqlTest extends TestCase
{
    use InteractsWithAccountingMySql;

    private bool $committedForConcurrency = false;

    protected Branch $branch;

    protected User $user;

    protected Payment $payment;

    protected function setUp(): void
    {
        parent::setUp();
        $this->requireAccountingMySql(true);
        $this->rebuildAccountingMySqlSchema();
        $this->branch = Branch::factory()->create(['currency' => 'JOD', 'timezone' => 'Asia/Amman']);
        $this->user = User::factory()->create(['branch_id' => $this->branch->id, 'is_active' => true]);
        $this->actingAs($this->user);
        app()->instance(FinancialAccountLookupInterface::class, new NullFinancialAccountLookup);
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
            'order_date' => now()->toDateString(),
        ]);
        $this->payment = Payment::create([
            'branch_id' => $this->branch->id,
            'order_id' => $order->id,
            'order_reference_no' => $order->reference_no,
            'method' => PaymentMethod::Card,
            'type' => PaymentType::Payment,
            'currency' => 'JOD',
            'currency_rate' => 1,
            'amount' => '10.345',
            'received_at' => now()->subHour(),
        ])->fresh();
        $snapshot = $this->payment->getAttributes();
        $this->artisan('migrate', [
            '--path' => module_path('DailyFinance', 'database/migrations'),
            '--realpath' => true,
            '--force' => true,
        ])->assertExitCode(0);
        $this->assertSame($snapshot, $this->payment->fresh()->getAttributes());
    }

    protected function payload(string $key): array
    {
        return [
            'branch_id' => $this->branch->id,
            'method' => 'card',
            'payment_ids' => [$this->payment->id],
            'source_hashes' => [$this->payment->id => PaymentSettlementSource::hash($this->payment)],
            'fees' => '0.045',
            'destination_name' => 'Branch bank',
            'reference' => 'GATEWAY-001',
            'request_key' => $key,
        ];
    }

    protected function credit(string $key): array
    {
        return [
            'amount' => '10.300',
            'received_date' => now($this->branch->timezone)->toDateString(),
            'reference' => 'BANK-001',
            'is_final' => true,
            'request_key' => $key,
        ];
    }

    public function test_overlapping_batches_cannot_reserve_the_same_collection(): void
    {
        $keys = [(string) Str::uuid(), (string) Str::uuid()];
        $results = $this->runAccountingMySqlWorkers(function (int $worker) use ($keys): string {
            $this->actingAs($this->user);
            try {
                app(PaymentSettlementServiceInterface::class)->create($this->payload($keys[$worker]));

                return 'saved';
            } catch (ValidationException) {
                return 'reserved';
            }
        })->sort()->values()->all();
        $this->assertSame(['reserved', 'saved'], $results);
        $this->assertSame(1, PaymentSettlement::count());
        $this->assertSame(1, PaymentSettlementItem::whereNotNull('active_payment_id')->count());
    }

    public function test_concurrent_batch_retries_return_one_record(): void
    {
        $payload = $this->payload((string) Str::uuid());
        $results = $this->runAccountingMySqlWorkers(function () use ($payload): string {
            $this->actingAs($this->user);

            return (string) app(PaymentSettlementServiceInterface::class)->create($payload)->id;
        });
        $this->assertCount(1, $results->unique());
        $this->assertSame(1, PaymentSettlement::count());
    }

    public function test_concurrent_credit_retries_append_one_receipt(): void
    {
        $record = app(PaymentSettlementServiceInterface::class)->create($this->payload((string) Str::uuid()));
        $credit = $this->credit((string) Str::uuid());
        $results = $this->runAccountingMySqlWorkers(function () use ($record, $credit): string {
            $this->actingAs($this->user);

            return app(PaymentSettlementServiceInterface::class)->receive($this->branch->id, $record->id, $credit)->status->value;
        })->all();
        $this->assertSame(['settled', 'settled'], $results);
        $this->assertSame(1, PaymentSettlementReceipt::count());
    }

    public function test_credit_and_cancellation_cannot_both_commit(): void
    {
        $record = app(PaymentSettlementServiceInterface::class)->create($this->payload((string) Str::uuid()));
        $credit = $this->credit((string) Str::uuid());
        $results = $this->runAccountingMySqlWorkers(function (int $worker) use ($record, $credit): string {
            $this->actingAs($this->user);
            try {
                $service = app(PaymentSettlementServiceInterface::class);
                if ($worker === 0) {
                    $service->cancel($this->branch->id, $record->id, 'Wrong gateway batch');
                } else {
                    $service->receive($this->branch->id, $record->id, $credit);
                }

                return 'saved';
            } catch (HttpException $exception) {
                return (string) $exception->getStatusCode();
            }
        })->sort()->values()->all();
        $this->assertSame(['409', 'saved'], $results);
        $record->refresh();
        $this->assertFalse($record->cancelled_at !== null && $record->receipts()->exists());
    }
}
