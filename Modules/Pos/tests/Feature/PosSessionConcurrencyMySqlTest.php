<?php

namespace Modules\Pos\Tests\Feature;

use Closure;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Modules\Branch\Models\Branch;
use Modules\Core\Contracts\DeliveryFeatureInterface;
use Modules\Core\Contracts\FinancialPostingBridgeInterface;
use Modules\Core\Services\Accounting\NullFinancialPostingBridge;
use Modules\Core\Services\Delivery\NullDeliveryFeatureService;
use Modules\Menu\Models\Menu;
use Modules\Order\Enums\OrderPaymentStatus;
use Modules\Order\Enums\OrderStatus;
use Modules\Order\Enums\OrderType;
use Modules\Order\Events\OrderPaid;
use Modules\Order\Models\Order;
use Modules\Order\Services\Checkout\PosCheckout;
use Modules\Order\Services\OrderPayment\OrderPaymentServiceInterface;
use Modules\Payment\Enums\PaymentMethod;
use Modules\Payment\Enums\PaymentType;
use Modules\Pos\Enums\PosCashDirection;
use Modules\Pos\Enums\PosCashReason;
use Modules\Pos\Enums\PosSessionStatus;
use Modules\Pos\Events\ClosePosSession;
use Modules\Pos\Models\PosCashMovement;
use Modules\Pos\Models\PosRegister;
use Modules\Pos\Models\PosSession;
use Modules\Pos\Services\PosCashMovement\PosCashMovementService;
use Modules\Pos\Services\PosCashMovement\PosCashMovementServiceInterface;
use Modules\Pos\Services\PosSession\PosSessionServiceInterface;
use Modules\Product\Models\Product;
use Modules\User\Models\User;
use RuntimeException;
use Spatie\Permission\Models\Permission;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\Concerns\InteractsWithAccountingMySql;
use Tests\TestCase;

class PosSessionConcurrencyMySqlTest extends TestCase
{
    use InteractsWithAccountingMySql;

    private bool $committedForConcurrency = false;

    protected function setUp(): void
    {
        parent::setUp();
        $this->requireAccountingMySql(true);
        if (DB::getDriverName() !== 'mysql') {
            $this->markTestSkipped('These gates exercise MySQL row locks and repeatable-read behavior.');
        }
        $this->rebuildAccountingMySqlSchema();
        Schema::disableForeignKeyConstraints();
        try {
            $this->artisan('migrate', [
                '--path' => array_map(fn ($module) => module_path($module, 'database/migrations'), ['Cart', 'Loyalty', 'Voucher', 'Media', 'Notification']),
                '--realpath' => true,
                '--force' => true,
            ])->assertExitCode(0);
        } finally {
            Schema::enableForeignKeyConstraints();
        }
        Schema::create('pos_close_test_events', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('session_id');
        });
        $this->app->instance(FinancialPostingBridgeInterface::class, new NullFinancialPostingBridge);
        $this->app->instance(DeliveryFeatureInterface::class, new NullDeliveryFeatureService);
        $this->app->instance('setting', new class
        {
            public function get(string $key, mixed $default = null): mixed
            {
                return match ($key) {
                    'default_currency' => 'USD',
                    'default_date_format' => 'Y-m-d',
                    'default_locale' => 'en',
                    'default_time_format' => 'h:i A',
                    'supported_locales' => ['en', 'ar'],
                    default => $default,
                };
            }
        });
        Notification::fake();
        Event::fake([OrderPaid::class]);
        // Posting is covered by the Accounting integration gates; record committed close dispatches across processes.
        Event::forget(ClosePosSession::class);
        Event::listen(ClosePosSession::class, fn (ClosePosSession $event) => DB::table('pos_close_test_events')->insert(['session_id' => $event->session->id]));
    }

    public function test_close_rejects_waiting_receipts_and_refunds_for_all_payment_methods(): void
    {
        foreach (PaymentMethod::cases() as $method) {
            foreach (PaymentType::cases() as $type) {
                [$session, $order] = $this->context();
                $results = $this->whileSessionLocked(
                    $session,
                    fn () => $this->closeSession($session),
                    function () use ($session, $order, $method, $type): string {
                        $order->storePayment(['method' => $method->value, 'amount' => '10', 'type' => $type, 'session' => $session]);

                        return 'payment_saved';
                    },
                );
                $this->assertSame(['closed', 'http:409'], $results->all(), $results->implode(' | '));
                $this->assertSame(0, $order->payments()->count());
                $this->assertSame(0, PosCashMovement::query()->where('pos_session_id', $session->id)->count());
                $this->assertSame('500.00', $session->fresh()->meta['cash_reconciliation']['expected_cash']);
            }
        }
    }

    public function test_committed_receipts_and_refunds_invalidate_the_waiting_review(): void
    {
        foreach (['cash', 'card', 'bank_transfer', 'mobile_wallet'] as $method) {
            foreach (PaymentType::cases() as $type) {
                [$session, $order] = $this->context();
                $token = app(PosSessionServiceInterface::class)->closingPreview($session->id, '500')['review']['token'];
                $results = $this->whileSessionLocked(
                    $session,
                    function () use ($session, $order, $method, $type): string {
                        $order->storePayment(['method' => $method, 'amount' => '10', 'type' => $type, 'session' => $session]);

                        return 'payment_saved';
                    },
                    fn () => $this->closeSession($session, '500', $token),
                );
                $this->assertSame(['payment_saved', 'http:409'], $results->all(), $results->implode(' | '));
                $this->assertSame(PosSessionStatus::Open, $session->fresh()->status);
                $this->assertSame(1, $order->payments()->count());
                $this->assertSame(0, DB::table('pos_close_test_events')->where('session_id', $session->id)->count());
            }
        }
    }

    public function test_late_collection_service_obeys_close_lock_and_does_not_partially_pay_an_order(): void
    {
        [$session, $order] = $this->context();
        $results = $this->whileSessionLocked(
            $session,
            fn () => $this->closeSession($session),
            function () use ($session, $order): string {
                app(OrderPaymentServiceInterface::class)->storePayment($order->id, [
                    'session_id' => $session->id,
                    'register_id' => $session->pos_register_id,
                    'payment_mode' => 'full',
                    'payments' => [['method' => 'cash', 'amount' => 20], ['method' => 'card', 'amount' => 30]],
                ]);

                return 'paid';
            },
        );
        $this->assertSame(['closed', 'http:409'], $results->all(), $results->implode(' | '));
        $this->assertSame(OrderPaymentStatus::Unpaid, $order->fresh()->payment_status);
        $this->assertEquals(50, $order->fresh()->due_amount->amount());
        $this->assertDatabaseCount('payments', 0);
        $this->assertDatabaseCount('pos_cash_movements', 0);
    }

    public function test_new_manual_cash_movement_waiting_for_close_is_rejected(): void
    {
        [$session] = $this->context();
        $results = $this->whileSessionLocked(
            $session,
            fn () => $this->closeSession($session),
            function () use ($session): string {
                app(PosCashMovementServiceInterface::class)->payOut($session, 20);

                return 'cash_paid_out';
            },
        );
        $this->assertSame(['closed', 'http:409'], $results->all(), $results->implode(' | '));
        $this->assertDatabaseCount('pos_cash_movements', 0);
        $this->assertSame('500.00', $session->fresh()->meta['cash_reconciliation']['expected_cash']);
    }

    public function test_committed_movement_is_included_by_waiting_legacy_close(): void
    {
        [$session] = $this->context();
        $results = $this->whileSessionLocked(
            $session,
            function () use ($session): string {
                app(PosCashMovementServiceInterface::class)->payIn($session, 20);

                return 'cash_received';
            },
            fn () => $this->closeSession($session, '520'),
        );
        $this->assertSame(['cash_received', 'closed'], $results->all(), $results->implode(' | '));
        $this->assertSame('520.00', $session->fresh()->meta['cash_reconciliation']['expected_cash']);
        $this->assertEquals(0, $session->fresh()->cash_over_short->amount());
    }

    public function test_payment_void_and_close_serialize_for_explicit_and_legacy_cash_attribution(): void
    {
        foreach ([true, false] as $explicit) {
            [$session, $order] = $this->context();
            $payment = $order->storePayment(['method' => 'cash', 'amount' => '10', 'session' => $session]);
            if (! $explicit) {
                $payment->update(['pos_session_id' => null]);
            }
            $results = $this->whileSessionLocked(
                $session,
                fn () => $this->closeSession($session, '510'),
                function () use ($order, $payment): string {
                    $order->voidPayment($payment, 'Void racing with close');

                    return 'voided';
                },
            );
            $this->assertSame(['closed', 'http:409'], $results->all(), $results->implode(' | '));
            $this->assertFalse($payment->fresh()->isVoided());
            $this->assertEquals(10, $session->fresh()->system_cash_sales->amount());
        }
    }

    public function test_parallel_close_confirms_once_with_one_adjustment_and_one_committed_event(): void
    {
        [$session] = $this->context();
        $token = app(PosSessionServiceInterface::class)->closingPreview($session->id, '499')['review']['token'];
        $results = $this->runAccountingMySqlWorkers(fn () => $this->httpResult(fn () => $this->closeSession($session, '499', $token)));
        $this->assertSame(['closed', 'http:400'], $results->sort()->values()->all(), $results->implode(' | '));
        $this->assertSame(1, PosCashMovement::query()->where('reason', PosCashReason::ClosingAdjust)->count());
        $this->assertSame(1, DB::table('pos_close_test_events')->where('session_id', $session->id)->count());
        $this->assertSame(auth()->id(), $session->fresh()->meta['closing_review']['verified_by']);
        $this->assertEquals(-1, $session->fresh()->cash_over_short->amount());
    }

    public function test_cashier_and_authorized_manager_race_preserves_only_the_winning_closers_custody(): void
    {
        [$session] = $this->context();
        $cashier = auth()->user();
        $manager = User::factory()->create(['branch_id' => $session->branch_id, 'is_active' => true]);
        $manager->givePermissionTo(Permission::findOrCreate('admin.pos_sessions.close_others'));
        $cashierToken = app(PosSessionServiceInterface::class)->closingPreview($session->id, '499')['review']['token'];
        $this->actingAs($manager);
        $managerToken = app(PosSessionServiceInterface::class)->closingPreview($session->id, '499')['review']['token'];

        $results = $this->runAccountingMySqlWorkers(function (int $worker) use ($session, $cashier, $manager, $cashierToken, $managerToken): string {
            $this->actingAs($worker === 0 ? $cashier : $manager);

            return $this->httpResult(function () use ($session, $worker, $cashierToken, $managerToken): string {
                app(PosSessionServiceInterface::class)->close($session->id, [
                    'declared_cash' => '499',
                    'review_token' => $worker === 0 ? $cashierToken : $managerToken,
                    'variance_reason' => 'Shortage verified after recounting.',
                    ...($worker === 1 ? ['closing_reason' => 'Closing after the cashier left.'] : []),
                ]);

                return 'closed';
            });
        });

        $this->assertSame(['closed', 'http:400'], $results->sort()->values()->all(), $results->implode(' | '));
        $closed = $session->fresh();
        $custody = $closed->meta['closing_custody'];
        $this->assertContains($closed->closed_by, [$cashier->id, $manager->id]);
        $this->assertSame($closed->closed_by, $custody['counted_by']['id']);
        $this->assertSame($closed->closed_by, $custody['approved_by']['id']);
        $this->assertSame($cashier->id, $custody['responsible_cashier']['id']);
        $this->assertSame($closed->closed_by === $manager->id, $custody['on_behalf']);
        $this->assertSame($custody['on_behalf'] ? 'Closing after the cashier left.' : null, $custody['closing_reason']);
        $this->assertSame('Shortage verified after recounting.', $custody['variance_reason']);
        $this->assertSame(1, PosCashMovement::query()->where('reason', PosCashReason::ClosingAdjust)->count());
        $this->assertSame(1, DB::table('pos_close_test_events')->where('session_id', $session->id)->count());
    }

    public function test_waiting_movement_uses_current_balance_even_if_caller_already_established_a_snapshot(): void
    {
        [$session] = $this->context();
        $ready = sys_get_temp_dir().'/pos-balance-snapshot-'.Str::uuid();
        try {
            $results = $this->runAccountingMySqlWorkers(function (int $worker) use ($session, $ready): string {
                return DB::transaction(function () use ($worker, $session, $ready): string {
                    if ($worker === 1) {
                        PosCashMovement::query()->count();
                        file_put_contents($ready.'.snapshot', 'ready');
                        $this->waitForFile($ready.'.movement');
                        file_put_contents($ready.'.waiting', 'ready');
                    } else {
                        $this->waitForFile($ready.'.snapshot');
                    }
                    $movement = app(PosCashMovementServiceInterface::class)->payIn($session, $worker === 0 ? 10 : 20);
                    if ($worker === 0) {
                        file_put_contents($ready.'.movement', 'ready');
                        $this->waitForFile($ready.'.waiting');
                        usleep(100_000);
                    }

                    return (string) $movement->getRawOriginal('balance_after');
                });
            });
            $this->assertEquals([510, 530], $results->map(fn ($amount) => (float) $amount)->all(), $results->implode(' | '));
            $this->assertDatabaseCount('pos_cash_movements', 2);
            $this->assertEquals(530, PosCashMovement::query()->latest('id')->firstOrFail()->balance_after->amount());
        } finally {
            foreach (['.snapshot', '.movement', '.waiting'] as $suffix) {
                @unlink($ready.$suffix);
            }
        }
    }

    public function test_parallel_movement_retries_save_one_row_and_reject_a_changed_payload(): void
    {
        [$session] = $this->context();
        $key = 'same-drawer-key';
        $results = $this->runAccountingMySqlWorkers(fn () => (string) app(PosCashMovementService::class)->addMovement(
            $session, PosCashReason::PayIn, '10', PosCashDirection::In, idempotencyKey: $key,
        )->id);
        $this->assertCount(1, $results->unique(), $results->implode(' | '));
        $this->assertFalse(str_starts_with($results->first(), 'error:'), $results->implode(' | '));
        $this->assertDatabaseCount('pos_cash_movements', 1);
        $changed = $this->httpResult(fn () => app(PosCashMovementService::class)->addMovement(
            $session, PosCashReason::PayIn, '11', PosCashDirection::In, idempotencyKey: $key,
        ));
        $this->assertSame('http:422', $changed);
        $this->assertDatabaseCount('pos_cash_movements', 1);
        $this->assertEquals(510, PosCashMovement::firstOrFail()->balance_after->amount());
    }

    public function test_cross_session_key_collision_does_not_deadlock_or_duplicate_cash(): void
    {
        [$first] = $this->context();
        [$second] = $this->context();
        $results = $this->runAccountingMySqlWorkers(function (int $worker) use ($first, $second): string {
            // Both sessions belong to different branches; each worker uses its own authorized cashier.
            $session = $worker === 0 ? $first : $second;
            $this->actingAs(User::withoutGlobalScopes()->findOrFail($session->opened_by));

            return $this->httpResult(function () use ($session): string {
                app(PosCashMovementService::class)->addMovement($session, PosCashReason::PayIn, '10', PosCashDirection::In, idempotencyKey: 'global-drawer-key');

                return 'saved';
            });
        });
        $this->assertSame(['http:422', 'saved'], $results->sort()->values()->all(), $results->implode(' | '));
        $this->assertSame(1, PosCashMovement::query()->withOutGlobalBranchPermission()->count());
    }

    public function test_checkout_waiting_for_close_creates_no_order_or_submission_receipt(): void
    {
        [$session] = $this->context();
        $menu = Menu::factory()->create(['branch_id' => $session->branch_id, 'is_active' => true]);
        $product = Product::factory()->create(['menu_id' => $menu->id, 'price' => 10, 'special_price' => null, 'is_active' => true]);
        $input = [
            'branch_id' => $session->branch_id,
            'register_id' => $session->pos_register_id,
            'session_id' => $session->id,
            'menu_id' => $menu->id,
            'type' => 'takeaway',
            'submit_action' => 'send_to_kitchen',
            'items' => [['line_id' => 'soup', 'product_id' => $product->id, 'qty' => 1, 'options' => []]],
        ];
        $input['quote_token'] = app(PosCheckout::class)->quote($input)['quote_token'];
        $input['idempotency_key'] = (string) Str::uuid();
        $results = $this->whileSessionLocked(
            $session,
            fn () => $this->closeSession($session),
            function () use ($input): string {
                app(PosCheckout::class)->submit($input);

                return 'order_saved';
            },
        );
        $this->assertSame(['closed', 'http:409'], $results->all(), $results->implode(' | '));
        $this->assertDatabaseCount('orders', 1);
        $this->assertDatabaseCount('order_submissions', 0);
    }

    private function whileSessionLocked(PosSession $session, Closure $holder, Closure $waiter): Collection
    {
        $signal = sys_get_temp_dir().'/pos-session-lock-'.Str::uuid();
        try {
            return $this->runAccountingMySqlWorkers(function (int $worker) use ($session, $holder, $waiter, $signal): string {
                DB::statement('SET SESSION innodb_lock_wait_timeout = 5');

                return $this->httpResult(function () use ($worker, $session, $holder, $waiter, $signal): mixed {
                    if ($worker === 0) {
                        return DB::transaction(function () use ($session, $holder, $signal): mixed {
                            PosSession::query()->whereKey($session->id)->lockForUpdate()->firstOrFail();
                            file_put_contents($signal.'.locked', 'ready');
                            $this->waitForFile($signal.'.waiting');
                            $result = $holder();
                            usleep(100_000);

                            return $result;
                        });
                    }
                    $this->waitForFile($signal.'.locked');
                    file_put_contents($signal.'.waiting', 'ready');

                    return $waiter();
                });
            });
        } finally {
            @unlink($signal.'.locked');
            @unlink($signal.'.waiting');
        }
    }

    private function waitForFile(string $file): void
    {
        $deadline = microtime(true) + 5;
        while (! file_exists($file)) {
            if (microtime(true) > $deadline) {
                throw new RuntimeException('The concurrent worker did not reach its lock barrier.');
            }
            usleep(1_000);
        }
    }

    private function httpResult(Closure $callback): string
    {
        try {
            return (string) $callback();
        } catch (HttpException $exception) {
            return 'http:'.$exception->getStatusCode();
        } catch (HttpResponseException $exception) {
            return 'http:'.$exception->getResponse()->getStatusCode();
        }
    }

    private function closeSession(PosSession $session, string $count = '500', ?string $token = null): string
    {
        app(PosSessionServiceInterface::class)->close($session->id, [
            'declared_cash' => $count,
            ...($token ? ['review_token' => $token] : []),
            ...($token ? ['variance_reason' => 'Concurrent closing count verified.'] : []),
        ]);

        return 'closed';
    }

    private function context(): array
    {
        $branch = Branch::factory()->create([
            'currency' => 'USD',
            'cash_difference_threshold' => 10,
            'order_types' => ['takeaway'],
            'payment_methods' => PaymentMethod::values(),
        ]);
        $user = User::factory()->create(['branch_id' => $branch->id, 'is_active' => true]);
        $this->actingAs($user);
        $register = PosRegister::factory()->create(['branch_id' => $branch->id, 'is_active' => true]);
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
