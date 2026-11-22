<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Modules\Invoice\Models\Invoice;
use Modules\Order\Models\Order;
use Modules\Payment\Models\Payment;
use Modules\Reservation\Models\Reservation;
use Modules\Reservation\Models\ReservationDepositTransaction;
use Modules\User\Models\User;

return new class extends Migration
{
    public function up(): void
    {
        $reservationsTable = (new Reservation)->getTable();
        $depositTransactionsTable = (new ReservationDepositTransaction)->getTable();

        Schema::table($reservationsTable, function (Blueprint $table) {
            $table->foreignIdFor(Order::class)->nullable()->after('customer_id')->constrained((new Order)->getTable())->nullOnDelete();
            $table->foreignIdFor(Invoice::class)->nullable()->after('order_id')->constrained((new Invoice)->getTable())->nullOnDelete();
        });

        Schema::create($depositTransactionsTable, function (Blueprint $table) use ($depositTransactionsTable) {
            $table->id();
            $table->branch();
            $table->foreignIdFor(Reservation::class)->constrained((new Reservation)->getTable())->restrictOnDelete();
            $table->foreignIdFor(User::class, 'customer_id')->nullable()->constrained((new User)->getTable())->nullOnDelete();
            $table->foreignIdFor(Order::class)->nullable()->constrained((new Order)->getTable())->nullOnDelete();
            $table->foreignIdFor(Invoice::class)->nullable()->constrained((new Invoice)->getTable())->nullOnDelete();
            $table->foreignIdFor(Payment::class)->nullable()->constrained((new Payment)->getTable())->nullOnDelete();
            $table->string('type', 24)->index();
            $table->decimal('amount', 18, 4);
            $table->decimal('balance_before', 18, 4);
            $table->decimal('balance_after', 18, 4);
            $table->string('currency', 3);
            $table->decimal('currency_rate', 18, 8);
            $table->string('tender_method', 32)->nullable();
            $table->string('financial_account_key')->nullable();
            $table->json('financial_account_snapshot')->nullable();
            $table->date('economic_date')->index();
            $table->string('idempotency_key')->unique();
            $table->foreignIdFor(ReservationDepositTransaction::class, 'original_transaction_id')->nullable();
            $table->foreignIdFor(ReservationDepositTransaction::class, 'reversed_by_transaction_id')->nullable();
            $table->foreign('original_transaction_id', 'res_deposit_original_fk')->references('id')->on($depositTransactionsTable)->restrictOnDelete();
            $table->foreign('reversed_by_transaction_id', 'res_deposit_reversed_by_fk')->references('id')->on($depositTransactionsTable)->restrictOnDelete();
            $table->timestamp('reversed_at')->nullable();
            $table->text('note')->nullable();
            $table->json('source_snapshot');
            $table->foreignIdFor(User::class, 'created_by')->nullable()->constrained((new User)->getTable())->nullOnDelete();
            $table->timestamps();
            $table->index(['reservation_id', 'economic_date'], 'reservation_deposit_date_idx');
        });

        $timestamp = now();
        DB::table($reservationsTable)
            ->where('deposit_paid_amount', '>', 0)
            ->orderBy('id')
            ->chunkById(250, function ($reservations) use ($depositTransactionsTable, $timestamp): void {
                foreach ($reservations as $reservation) {
                    DB::table($depositTransactionsTable)->insert([
                        'branch_id' => $reservation->branch_id,
                        'reservation_id' => $reservation->id,
                        'customer_id' => $reservation->customer_id,
                        'type' => 'opening',
                        'amount' => $reservation->deposit_paid_amount,
                        'balance_before' => 0,
                        'balance_after' => $reservation->deposit_paid_amount,
                        'currency' => $reservation->deposit_currency,
                        'currency_rate' => $reservation->deposit_currency_rate,
                        'economic_date' => substr((string) ($reservation->created_at ?: $timestamp), 0, 10),
                        'idempotency_key' => 'reservation-deposit-opening-'.$reservation->id,
                        'note' => 'Reviewed legacy opening deposit balance.',
                        'source_snapshot' => json_encode([
                            'source_version' => 1,
                            'legacy_deposit_paid_amount' => $reservation->deposit_paid_amount,
                            'requires_opening_journal_review' => true,
                        ], JSON_THROW_ON_ERROR),
                        'created_at' => $timestamp,
                        'updated_at' => $timestamp,
                    ]);
                }
            });
    }

    public function down(): void
    {
        Schema::dropIfExists((new ReservationDepositTransaction)->getTable());
        Schema::table((new Reservation)->getTable(), function (Blueprint $table) {
            $table->dropForeign(['order_id']);
            $table->dropForeign(['invoice_id']);
            $table->dropColumn(['order_id', 'invoice_id']);
        });
    }
};
