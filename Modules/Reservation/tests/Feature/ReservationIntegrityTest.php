<?php

namespace Modules\Reservation\tests\Feature;

use Carbon\Carbon;
use Illuminate\Contracts\Queue\ShouldQueueAfterCommit;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use LogicException;
use Modules\Branch\Models\Branch;
use Modules\Core\Contracts\FinancialAccountLookupInterface;
use Modules\Core\Services\Accounting\NullFinancialAccountLookup;
use Modules\Currency\Models\CurrencyRate;
use Modules\Invoice\Enums\InvoicePartyType;
use Modules\Invoice\Models\Invoice;
use Modules\Invoice\Models\InvoiceParty;
use Modules\Invoice\Services\PaymentAllocation\PaymentAllocationServiceInterface;
use Modules\Order\Enums\OrderPaymentStatus;
use Modules\Order\Enums\OrderStatus;
use Modules\Order\Enums\OrderType;
use Modules\Order\Models\Order;
use Modules\Payment\Enums\PaymentMethod;
use Modules\Reservation\Enums\ReservationDepositTransactionType;
use Modules\Reservation\Enums\ReservationStatus;
use Modules\Reservation\Enums\ReservationWaitlistStatus;
use Modules\Reservation\Jobs\SendWaitlistNotificationJob;
use Modules\Reservation\Listeners\SendReservationCreatedNotification;
use Modules\Reservation\Listeners\SendReservationStatusChangedNotification;
use Modules\Reservation\Listeners\SendReservationTablesAssignedNotification;
use Modules\Reservation\Listeners\SendReservationUpdatedNotification;
use Modules\Reservation\Listeners\SendWaitlistConvertedNotification;
use Modules\Reservation\Models\Reservation;
use Modules\Reservation\Models\ReservationTableBlock;
use Modules\Reservation\Models\ReservationWaitlist;
use Modules\Reservation\Services\Reservation\ReservationServiceInterface;
use Modules\Reservation\Services\ReservationAvailability\ReservationAvailabilityServiceInterface;
use Modules\Reservation\Services\ReservationDeposit\ReservationDepositServiceInterface;
use Modules\Reservation\Services\ReservationSetting\ReservationSettingServiceInterface;
use Modules\Reservation\Services\ReservationStatus\ReservationStatusServiceInterface;
use Modules\Reservation\Services\ReservationTableAssignment\ReservationTableAssignmentServiceInterface;
use Modules\Reservation\Services\ReservationWaitlist\ReservationWaitlistServiceInterface;
use Modules\Reservation\Transformers\Api\V1\ReservationDepositTransactionResource;
use Modules\Reservation\Transformers\Api\V1\ReservationResource;
use Modules\SeatingPlan\Enums\TableStatus;
use Modules\SeatingPlan\Models\Floor;
use Modules\SeatingPlan\Models\Table;
use Modules\SeatingPlan\Models\Zone;
use Modules\User\Models\Permission;
use Modules\User\Models\User;
use RuntimeException;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

class ReservationIntegrityTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->app->instance(FinancialAccountLookupInterface::class, new NullFinancialAccountLookup);
    }

    public function test_deposit_idempotency_key_can_only_replay_the_identical_request(): void
    {
        $reservation = $this->depositReservation();
        $service = app(ReservationDepositServiceInterface::class);
        $payload = [
            'type' => ReservationDepositTransactionType::Collection,
            'amount' => '10.000',
            'tender_method' => 'cash',
            'economic_date' => now()->toDateString(),
            'idempotency_key' => 'reservation-integrity-idempotency',
            'note' => 'First collection.',
        ];

        $first = $service->record($reservation->id, $payload);
        $replay = $service->record($reservation->id, $payload);

        $this->assertSame($first->id, $replay->id);
        $this->assertSame(1, $reservation->depositTransactions()->count());

        $this->expectException(HttpException::class);
        $service->record($reservation->id, [
            ...$payload,
            'note' => 'A different request must not reuse the key.',
        ]);
    }

    public function test_a_reversal_transaction_cannot_itself_be_reversed(): void
    {
        $reservation = $this->depositReservation();
        $service = app(ReservationDepositServiceInterface::class);
        $collection = $service->record($reservation->id, [
            'type' => ReservationDepositTransactionType::Collection,
            'amount' => '10.000',
            'tender_method' => 'cash',
            'economic_date' => now()->toDateString(),
            'idempotency_key' => 'reservation-integrity-collection',
            'note' => 'Collected.',
        ]);
        $reversal = $service->reverse($collection->id, [
            'economic_date' => now()->toDateString(),
            'idempotency_key' => 'reservation-integrity-reversal',
            'reason' => 'Operator correction.',
        ]);

        $this->expectException(HttpException::class);
        $service->reverse($reversal->id, [
            'economic_date' => now()->toDateString(),
            'idempotency_key' => 'reservation-integrity-reverse-reversal',
            'reason' => 'Invalid second reversal.',
        ]);
    }

    public function test_deposit_ledger_financial_facts_are_immutable(): void
    {
        $reservation = $this->depositReservation();
        $transaction = app(ReservationDepositServiceInterface::class)->record($reservation->id, [
            'type' => ReservationDepositTransactionType::Collection,
            'amount' => '10.000',
            'tender_method' => 'cash',
            'economic_date' => now()->toDateString(),
            'idempotency_key' => 'reservation-integrity-immutable',
            'note' => 'Immutable collection.',
        ]);

        $this->expectException(LogicException::class);
        $transaction->update(['amount' => '1.000']);
    }

    public function test_collection_is_rejected_after_the_reservation_is_terminal(): void
    {
        $reservation = $this->depositReservation([
            'status' => ReservationStatus::Cancelled,
        ]);

        $this->expectException(HttpException::class);
        app(ReservationDepositServiceInterface::class)->record($reservation->id, [
            'type' => ReservationDepositTransactionType::Collection,
            'amount' => '10.000',
            'tender_method' => 'cash',
            'economic_date' => now()->toDateString(),
            'idempotency_key' => 'reservation-integrity-terminal',
            'note' => 'Must be rejected.',
        ]);
    }

    public function test_collection_is_rejected_when_the_reservation_does_not_require_a_deposit(): void
    {
        $reservation = Reservation::factory()->create([
            'deposit_required' => false,
        ]);

        $this->expectException(HttpException::class);
        app(ReservationDepositServiceInterface::class)->record($reservation->id, [
            'type' => ReservationDepositTransactionType::Collection,
            'amount' => '10.000',
            'tender_method' => 'cash',
            'economic_date' => now()->toDateString(),
            'idempotency_key' => 'reservation-integrity-not-required',
            'note' => 'Must be rejected.',
        ]);
    }

    public function test_deposit_collection_freezes_source_to_default_currency_rate_and_base_values(): void
    {
        CurrencyRate::query()->updateOrCreate(
            ['currency' => 'USD'],
            ['rate' => '1.41000000'],
        );
        $branch = Branch::factory()->create(['currency' => 'USD']);
        $reservation = $this->depositReservation([
            'branch_id' => $branch->id,
            'deposit_currency' => 'USD',
            'deposit_currency_rate' => '0.70921986',
        ]);

        $transaction = app(ReservationDepositServiceInterface::class)->record($reservation->id, [
            'type' => ReservationDepositTransactionType::Collection,
            'amount' => '10.00',
            'tender_method' => 'cash',
            'economic_date' => now()->toDateString(),
            'idempotency_key' => 'reservation-integrity-usd-rate',
            'note' => 'USD collection.',
        ]);

        $this->assertSame('0.70921986', $transaction->getRawOriginal('currency_rate'));
        $this->assertSame('7.09219860', $transaction->getRawOriginal('base_amount'));
        $this->assertSame('7.09219860', $transaction->getRawOriginal('base_balance_after'));
        $resource = (new ReservationDepositTransactionResource($transaction))->toArray(request());
        $this->assertEqualsWithDelta(
            7.0921986,
            $resource['amount']['converted']->amount(),
            0.000000001,
        );
        $this->assertEqualsWithDelta(
            0.70921986,
            (float) $reservation->fresh()->getRawOriginal('deposit_currency_rate'),
            0.000000001,
        );
    }

    public function test_refund_is_rejected_when_branch_policy_disallows_refunds(): void
    {
        $reservation = $this->depositReservation([
            'deposit_paid_amount' => 10,
        ]);
        app(ReservationSettingServiceInterface::class)
            ->forBranch($reservation->branch_id)
            ->update(['deposit_refundable' => false]);
        $resource = (new ReservationResource(
            $reservation->load('reservationSetting')
        ))->toArray(request());
        $this->assertFalse($resource['deposit_policy']['refundable']);

        $this->expectException(HttpException::class);
        app(ReservationDepositServiceInterface::class)->record($reservation->id, [
            'type' => ReservationDepositTransactionType::Refund,
            'amount' => '5.000',
            'tender_method' => 'cash',
            'economic_date' => now()->toDateString(),
            'idempotency_key' => 'reservation-integrity-non-refundable',
            'note' => 'Must be rejected by policy.',
        ]);
    }

    public function test_deposit_application_targets_an_open_order_and_is_allocated_when_the_invoice_is_created(): void
    {
        $branch = Branch::factory()->create(['currency' => 'JOD']);
        $reservation = $this->depositReservation(['branch_id' => $branch->id]);
        $order = $this->openOrder($branch, total: 100);
        $service = app(ReservationDepositServiceInterface::class);

        $service->record($reservation->id, [
            'type' => ReservationDepositTransactionType::Collection,
            'amount' => '30.000',
            'tender_method' => PaymentMethod::Cash->value,
            'economic_date' => now()->toDateString(),
            'idempotency_key' => 'reservation-order-application-collection',
            'note' => 'Collected before the order was opened.',
        ]);

        $eligibleOrders = $service->searchEligibleOrders($reservation->id, $order->reference_no);
        $this->assertCount(1, $eligibleOrders);
        $this->assertSame($order->id, $eligibleOrders[0]['order_id']);
        $this->assertArrayNotHasKey('invoice_id', $eligibleOrders[0]);

        $application = $service->record($reservation->id, [
            'type' => ReservationDepositTransactionType::Application,
            'amount' => '30.000',
            'order_id' => $order->id,
            'economic_date' => now()->toDateString(),
            'idempotency_key' => 'reservation-order-application',
            'note' => 'Apply the held deposit to the order.',
        ]);

        $payment = $application->payment()->firstOrFail();
        $this->assertNull($application->invoice_id);
        $this->assertSame(PaymentMethod::ReservationDeposit, $payment->method);
        $this->assertSame($reservation->id, $order->fresh()->reservation_id);
        $this->assertEqualsWithDelta(70, $order->fresh()->getRawOriginal('due_amount'), 0.0001);
        $this->assertSame(0, $payment->allocations()->count());

        $invoice = $this->issuedInvoice($order, total: 100);
        app(PaymentAllocationServiceInterface::class)->allocate(
            $invoice,
            collect([$order->fresh()->load('payments')]),
        );

        $this->assertSame(1, $payment->allocations()->count());
        $this->assertEqualsWithDelta(30, $invoice->fresh()->getRawOriginal('net_paid'), 0.0001);

        $resource = (new ReservationDepositTransactionResource(
            $service->show($application->id)
        ))->toArray(request());
        $this->assertSame($invoice->id, $resource['invoice']['id']);
        $this->assertSame($invoice->invoice_number, $resource['invoice']['invoice_number']);
    }

    public function test_reversing_an_allocated_order_deposit_refreshes_order_and_invoice_balances(): void
    {
        $branch = Branch::factory()->create(['currency' => 'JOD']);
        $reservation = $this->depositReservation(['branch_id' => $branch->id]);
        $order = $this->openOrder($branch, total: 100);
        $service = app(ReservationDepositServiceInterface::class);

        $service->record($reservation->id, [
            'type' => ReservationDepositTransactionType::Collection,
            'amount' => '25.000',
            'tender_method' => PaymentMethod::Cash->value,
            'economic_date' => now()->toDateString(),
            'idempotency_key' => 'reservation-reversal-collection',
            'note' => 'Collected.',
        ]);
        $application = $service->record($reservation->id, [
            'type' => ReservationDepositTransactionType::Application,
            'amount' => '25.000',
            'order_id' => $order->id,
            'economic_date' => now()->toDateString(),
            'idempotency_key' => 'reservation-reversal-application',
            'note' => 'Applied.',
        ]);
        $invoice = $this->issuedInvoice($order, total: 100);
        app(PaymentAllocationServiceInterface::class)->allocate(
            $invoice,
            collect([$order->fresh()->load('payments')]),
        );

        $service->reverse($application->id, [
            'economic_date' => now()->toDateString(),
            'idempotency_key' => 'reservation-reversal-application-reversed',
            'reason' => 'Order payment was corrected.',
        ]);

        $this->assertNotNull($application->payment()->firstOrFail()->voided_at);
        $this->assertEqualsWithDelta(100, $order->fresh()->getRawOriginal('due_amount'), 0.0001);
        $this->assertEqualsWithDelta(0, $invoice->fresh()->getRawOriginal('net_paid'), 0.0001);
        $this->assertEqualsWithDelta(25, $reservation->fresh()->getRawOriginal('deposit_paid_amount'), 0.0001);
    }

    public function test_converted_waitlist_entries_cannot_be_cancelled_again(): void
    {
        $waitlist = ReservationWaitlist::factory()->create([
            'status' => ReservationWaitlistStatus::Converted,
        ]);

        $this->expectException(HttpException::class);
        app(ReservationWaitlistServiceInterface::class)->cancel($waitlist->id, [
            'reason' => 'Invalid cancellation.',
        ]);
    }

    public function test_waitlist_notification_is_queued_and_terminal_failure_restores_a_retryable_state(): void
    {
        Queue::fake();
        $waitlist = ReservationWaitlist::factory()->create([
            'status' => ReservationWaitlistStatus::Waiting,
        ]);

        $notified = app(ReservationWaitlistServiceInterface::class)->notify($waitlist->id);

        $this->assertSame(ReservationWaitlistStatus::Notified, $notified->status);
        $this->assertNotNull($notified->notified_at);
        Queue::assertPushed(
            SendWaitlistNotificationJob::class,
            fn (SendWaitlistNotificationJob $job): bool => $job->waitlistId === $waitlist->id
                && $job->notifiedAt === $notified->notified_at->toDateTimeString(),
        );

        (new SendWaitlistNotificationJob(
            $waitlist->id,
            $notified->notified_at->toDateTimeString(),
        ))->failed(new RuntimeException('Notification provider unavailable.'));

        $waitlist->refresh();
        $this->assertSame(ReservationWaitlistStatus::Waiting, $waitlist->status);
        $this->assertNull($waitlist->notified_at);
    }

    public function test_reservation_notification_listeners_are_queued_after_commit(): void
    {
        $listeners = [
            new SendReservationCreatedNotification,
            new SendReservationUpdatedNotification,
            new SendReservationStatusChangedNotification,
            new SendReservationTablesAssignedNotification,
            new SendWaitlistConvertedNotification,
        ];

        foreach ($listeners as $listener) {
            $this->assertInstanceOf(ShouldQueueAfterCommit::class, $listener);
            $this->assertSame(5, $listener->tries);
            $this->assertSame([10, 30, 60, 180], $listener->backoff());
        }
    }

    public function test_reservation_update_locks_the_current_record_before_building_its_payload(): void
    {
        $reservation = Reservation::factory()->create();

        DB::flushQueryLog();
        DB::enableQueryLog();

        app(ReservationServiceInterface::class)->update($reservation->id, []);

        $queries = DB::getQueryLog();
        DB::disableQueryLog();

        $reservationSelect = collect($queries)->first(function (array $query): bool {
            $sql = strtolower($query['query']);

            return str_starts_with($sql, 'select')
                && str_contains($sql, 'reservations')
                && str_contains($sql, 'reservation_number');
        });

        $this->assertNotNull($reservationSelect);

        if (DB::getDriverName() === 'sqlite') {
            $this->assertSame('write', $reservationSelect['readWriteType'] ?? null);
        } else {
            $this->assertStringContainsString('for update', strtolower($reservationSelect['query']));
        }
    }

    public function test_active_reservations_cannot_be_deleted(): void
    {
        $reservation = Reservation::factory()->create([
            'status' => ReservationStatus::Confirmed,
        ]);

        $this->expectException(HttpException::class);
        app(ReservationServiceInterface::class)->destroy($reservation->id);
    }

    public function test_status_endpoint_requires_the_permission_for_the_exact_target_status(): void
    {
        $reservation = Reservation::factory()->create([
            'status' => ReservationStatus::Pending,
        ]);
        $user = User::factory()->create();
        Permission::query()->firstOrCreate([
            'name' => 'admin.reservations.cancel',
            'guard_name' => 'api',
        ]);
        $user->givePermissionTo('admin.reservations.cancel');

        $this->actingAs($user)
            ->patchJson("/api/v1/reservations/$reservation->id/status", [
                'status' => ReservationStatus::Confirmed->value,
            ])
            ->assertForbidden();

        $this->assertSame(ReservationStatus::Pending, $reservation->fresh()->status);
    }

    public function test_required_cancellation_reason_is_returned_as_a_field_validation_error(): void
    {
        $reservation = Reservation::factory()->create([
            'status' => ReservationStatus::Pending,
        ]);
        app(ReservationSettingServiceInterface::class)
            ->forBranch($reservation->branch_id)
            ->update([
                'cancellation_allowed' => true,
                'cancellation_deadline_minutes' => null,
                'require_cancellation_reason' => true,
            ]);
        $user = User::factory()->create();
        Permission::query()->firstOrCreate([
            'name' => 'admin.reservations.cancel',
            'guard_name' => 'api',
        ]);
        $user->givePermissionTo('admin.reservations.cancel');

        $this->actingAs($user)
            ->patchJson("/api/v1/reservations/$reservation->id/status", [
                'status' => ReservationStatus::Cancelled->value,
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('reason');

        $this->assertSame(ReservationStatus::Pending, $reservation->fresh()->status);
    }

    public function test_assigning_a_table_to_a_confirmed_reservation_updates_the_live_table_state(): void
    {
        $reservation = Reservation::factory()->create([
            'status' => ReservationStatus::Confirmed,
            'reservation_date' => now()->addDays(2)->toDateString(),
            'start_time' => '18:00:00',
            'end_time' => '19:30:00',
            'start_at' => now()->addDays(2)->setTime(18, 0),
            'end_at' => now()->addDays(2)->setTime(19, 30),
            'guest_count' => 2,
        ]);
        $floor = Floor::factory()->create([
            'branch_id' => $reservation->branch_id,
            'name' => ['en' => 'Main floor', 'ar' => 'الطابق الرئيسي'],
            'is_active' => true,
        ]);
        $zone = Zone::factory()->create([
            'branch_id' => $reservation->branch_id,
            'floor_id' => $floor->id,
            'name' => ['en' => 'Main zone', 'ar' => 'المنطقة الرئيسية'],
            'is_active' => true,
        ]);
        $table = Table::factory()->create([
            'branch_id' => $reservation->branch_id,
            'floor_id' => $floor->id,
            'zone_id' => $zone->id,
            'capacity' => 4,
            'status' => TableStatus::Available,
        ]);

        app(ReservationTableAssignmentServiceInterface::class)->assign(
            $reservation->id,
            [$table->id],
        );

        $this->assertSame(TableStatus::Reserved, $table->fresh()->status);
        $this->assertTrue($reservation->fresh()->tables->contains($table->id));
    }

    public function test_reservation_completion_keeps_a_table_with_an_active_order_occupied(): void
    {
        $reservation = Reservation::factory()->create(['status' => ReservationStatus::Seated]);
        $table = $this->tableForReservation($reservation, TableStatus::Occupied);
        $reservation->tables()->attach($table->id);
        $order = $this->openOrder($reservation->branch, 20);
        $order->update(['table_id' => $table->id, 'status' => OrderStatus::Preparing]);

        app(ReservationStatusServiceInterface::class)->transition($reservation, ReservationStatus::Completed);

        $this->assertSame(ReservationStatus::Completed, $reservation->fresh()->status);
        $this->assertSame(TableStatus::Occupied, $table->fresh()->status);
        $this->assertSame(OrderStatus::Preparing, $order->fresh()->status);
    }

    public function test_reservation_completion_releases_a_table_without_active_orders(): void
    {
        $reservation = Reservation::factory()->create(['status' => ReservationStatus::Seated]);
        $table = $this->tableForReservation($reservation, TableStatus::Occupied);
        $reservation->tables()->attach($table->id);
        $order = $this->openOrder($reservation->branch, 20);
        $order->update(['table_id' => $table->id, 'status' => OrderStatus::Completed, 'payment_status' => OrderPaymentStatus::Paid]);

        app(ReservationStatusServiceInterface::class)->transition($reservation, ReservationStatus::Completed);

        $this->assertSame(TableStatus::Available, $table->fresh()->status);
    }

    public function test_confirming_a_reservation_does_not_overwrite_an_active_orders_table_status(): void
    {
        $reservation = Reservation::factory()->create(['status' => ReservationStatus::Pending]);
        $table = $this->tableForReservation($reservation, TableStatus::Occupied);
        $reservation->tables()->attach($table->id);
        $this->openOrder($reservation->branch, 20)->update(['table_id' => $table->id]);

        app(ReservationStatusServiceInterface::class)->transition($reservation, ReservationStatus::Confirmed);

        $this->assertSame(TableStatus::Occupied, $table->fresh()->status);
    }

    public function test_removing_a_reservation_table_does_not_release_an_active_order(): void
    {
        $reservation = Reservation::factory()->create(['status' => ReservationStatus::Confirmed]);
        $table = $this->tableForReservation($reservation, TableStatus::Reserved);
        $reservation->tables()->attach($table->id);
        $this->openOrder($reservation->branch, 20)->update(['table_id' => $table->id]);

        app(ReservationTableAssignmentServiceInterface::class)->remove($reservation, $table->id);

        $this->assertFalse($reservation->fresh()->tables->contains($table->id));
        $this->assertSame(TableStatus::Reserved, $table->fresh()->status);
    }

    public function test_assigning_a_future_reservation_preserves_an_occupied_table(): void
    {
        $reservation = Reservation::factory()->create([
            'status' => ReservationStatus::Confirmed,
            'reservation_date' => now()->addDays(2)->toDateString(),
            'start_time' => '18:00:00',
            'end_time' => '19:30:00',
            'start_at' => now()->addDays(2)->setTime(18, 0),
            'end_at' => now()->addDays(2)->setTime(19, 30),
            'guest_count' => 2,
        ]);
        $table = $this->tableForReservation($reservation, TableStatus::Occupied);
        $this->openOrder($reservation->branch, 20)->update(['table_id' => $table->id]);

        app(ReservationTableAssignmentServiceInterface::class)->assign($reservation, [$table->id]);

        $this->assertSame(TableStatus::Occupied, $table->fresh()->status);
        $this->assertTrue($reservation->fresh()->tables->contains($table->id));
    }

    public function test_reservation_status_batches_active_order_checks_for_all_tables(): void
    {
        foreach ([1, 4] as $tableCount) {
            $reservation = Reservation::factory()->create(['status' => ReservationStatus::Seated]);
            $tables = collect();
            for ($i = 0; $i < $tableCount; $i++) {
                $table = $this->tableForReservation($reservation, TableStatus::Occupied);
                $reservation->tables()->attach($table->id);
                $this->openOrder($reservation->branch, 20)->update(['table_id' => $table->id]);
                $tables->push($table);
            }

            $queries = $this->countActiveOrderLookups(fn () => app(ReservationStatusServiceInterface::class)
                ->transition($reservation, ReservationStatus::Completed));

            $this->assertSame(1, $queries, "Active-order lookup count for $tableCount tables");
            foreach ($tables as $table) {
                $this->assertSame(TableStatus::Occupied, $table->fresh()->status);
            }
        }
    }

    public function test_reassignment_batches_active_order_checks_for_added_and_removed_tables(): void
    {
        foreach ([1, 4] as $tableCount) {
            $reservation = Reservation::factory()->create([
                'status' => ReservationStatus::Confirmed,
                'reservation_date' => now()->addDays(2)->toDateString(),
                'start_time' => '18:00:00',
                'end_time' => '19:30:00',
                'start_at' => now()->addDays(2)->setTime(18, 0),
                'end_at' => now()->addDays(2)->setTime(19, 30),
                'guest_count' => 2,
            ]);
            $removedTables = collect();
            $assignedTables = collect();
            for ($i = 0; $i < $tableCount; $i++) {
                $removed = $this->tableForReservation($reservation, TableStatus::Reserved);
                $reservation->tables()->attach($removed->id);
                $this->openOrder($reservation->branch, 20)->update(['table_id' => $removed->id]);
                $removedTables->push($removed);
                $assigned = $this->tableForReservation($reservation, TableStatus::Occupied);
                $this->openOrder($reservation->branch, 20)->update(['table_id' => $assigned->id]);
                $assignedTables->push($assigned);
            }

            $queries = $this->countActiveOrderLookups(fn () => app(ReservationTableAssignmentServiceInterface::class)
                ->assign($reservation, $assignedTables->pluck('id')->all()));

            $this->assertSame(1, $queries, "Active-order lookup count for $tableCount added and removed tables");
            foreach ($removedTables as $table) {
                $this->assertSame(TableStatus::Reserved, $table->fresh()->status);
            }
            foreach ($assignedTables as $table) {
                $this->assertSame(TableStatus::Occupied, $table->fresh()->status);
            }
            $this->assertEqualsCanonicalizing($assignedTables->pluck('id')->all(), $reservation->fresh()->tables->modelKeys());
        }
    }

    public function test_a_branch_wide_table_block_blocks_every_table_in_the_window(): void
    {
        $reservation = Reservation::factory()->create();
        $floor = Floor::factory()->create([
            'branch_id' => $reservation->branch_id,
            'name' => ['en' => 'Main floor', 'ar' => 'الطابق الرئيسي'],
            'is_active' => true,
        ]);
        $zone = Zone::factory()->create([
            'branch_id' => $reservation->branch_id,
            'floor_id' => $floor->id,
            'name' => ['en' => 'Main zone', 'ar' => 'المنطقة الرئيسية'],
            'is_active' => true,
        ]);
        $table = Table::factory()->create([
            'branch_id' => $reservation->branch_id,
            'floor_id' => $floor->id,
            'zone_id' => $zone->id,
            'capacity' => 4,
            'status' => TableStatus::Available,
        ]);
        $date = now()->addDays(2)->toDateString();
        $start = Carbon::parse("$date 18:00", 'Asia/Amman')->utc();
        ReservationTableBlock::factory()->create([
            'branch_id' => $reservation->branch_id,
            'table_id' => null,
            'start_at' => $start,
            'end_at' => $start->copy()->addHours(2),
        ]);

        $result = app(ReservationAvailabilityServiceInterface::class)->check([
            'branch_id' => $reservation->branch_id,
            'reservation_date' => $date,
            'start_time' => '18:30',
            'duration_minutes' => 60,
            'guest_count' => 2,
            'table_ids' => [$table->id],
        ]);

        $this->assertFalse($result['available']);
        $this->assertNotEmpty($result['warnings']);
    }

    private function depositReservation(array $attributes = []): Reservation
    {
        return Reservation::factory()->create([
            'deposit_required' => true,
            'deposit_amount' => 20,
            'deposit_paid_amount' => 0,
            'deposit_currency' => 'JOD',
            'deposit_currency_rate' => 1,
            ...$attributes,
        ]);
    }

    private function countActiveOrderLookups(callable $operation): int
    {
        DB::flushQueryLog();
        DB::enableQueryLog();
        try {
            $operation();

            return collect(DB::getQueryLog())->filter(fn (array $query) => str_contains(strtolower($query['query']), 'exists') && str_contains($query['query'], 'orders')
            )->count();
        } finally {
            DB::disableQueryLog();
            DB::flushQueryLog();
        }
    }

    private function tableForReservation(Reservation $reservation, TableStatus $status): Table
    {
        $floor = Floor::factory()->create(['name' => ['en' => 'Main floor'], 'branch_id' => $reservation->branch_id, 'is_active' => true]);
        $zone = Zone::factory()->create(['name' => ['en' => 'Main zone'], 'branch_id' => $reservation->branch_id, 'floor_id' => $floor->id, 'is_active' => true]);

        return Table::factory()->create([
            'branch_id' => $reservation->branch_id,
            'floor_id' => $floor->id,
            'zone_id' => $zone->id,
            'capacity' => 4,
            'status' => $status,
        ]);
    }

    private function openOrder(Branch $branch, float $total): Order
    {
        return Order::query()->create([
            'branch_id' => $branch->id,
            'status' => OrderStatus::Confirmed,
            'type' => OrderType::DineIn,
            'payment_status' => OrderPaymentStatus::Unpaid,
            'currency' => $branch->currency,
            'currency_rate' => 1,
            'subtotal' => $total,
            'total' => $total,
            'due_amount' => $total,
            'guest_count' => 2,
            'order_date' => now(),
        ]);
    }

    private function issuedInvoice(Order $order, float $total): Invoice
    {
        $seller = InvoiceParty::query()->create([
            'type' => InvoicePartyType::Seller,
            'legal_name' => 'Reservation Test Seller',
            'country_code' => 'JO',
        ]);

        return Invoice::query()->create([
            'branch_id' => $order->branch_id,
            'order_id' => $order->id,
            'seller_party_id' => $seller->id,
            'invoice_number' => 'INV-RSV-'.$order->id,
            'invoice_counter' => $order->id,
            'currency' => $order->currency,
            'currency_rate' => $order->currency_rate,
            'subtotal' => $total,
            'tax_total' => 0,
            'discount_total' => 0,
            'total' => $total,
            'issued_at' => now(),
        ]);
    }
}
