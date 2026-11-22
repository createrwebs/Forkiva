<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Modules\Branch\Models\Branch;
use Modules\DailyFinance\Models\PaymentSettlement;
use Modules\Payment\Models\Payment;
use Modules\User\Models\User;

return new class extends Migration
{
    /**
     * Preserve bank-credit evidence separately from source collections and optional ledger posting.
     */
    public function up(): void
    {
        Schema::create('daily_finance_payment_settlements', function (Blueprint $table): void {
            $table->id();
            $table->string('reference_no', 32)->nullable()->unique('df_ps_reference_unique');
            $table->foreignIdFor(Branch::class)->constrained()->restrictOnDelete();
            $table->createdBy(false)->nullable(false)->constrained((new User)->getTable())->restrictOnDelete();
            $table->string('method');
            $table->string('currency', 3);
            $table->string('timezone');
            $table->string('status')->default('pending');
            $table->string('reference', 120);
            $table->string('account_key')->nullable();
            $table->string('destination_name', 160);
            $table->char('account_fingerprint', 64);
            $table->decimal('gross_amount', 18, 4);
            $table->decimal('fees', 18, 4);
            $table->decimal('expected_amount', 18, 4);
            $table->uuid('request_key');
            $table->char('request_hash', 64);
            $table->text('notes')->nullable();
            $table->foreignIdFor(User::class, 'cancelled_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestamp('cancelled_at')->nullable();
            $table->text('cancellation_reason')->nullable();
            $table->timestamps();
            $table->unique(['branch_id', 'request_key'], 'payment_settlements_request_unique');
            $table->index(['branch_id', 'status', 'created_at'], 'payment_settlements_branch_status');
        });

        Schema::create('daily_finance_payment_settlement_items', function (Blueprint $table): void {
            $table->id();
            $table->foreignIdFor(PaymentSettlement::class)->constrained('daily_finance_payment_settlements', indexName: 'payment_settlement_items_batch_foreign')->restrictOnDelete();
            $table->foreignIdFor(Payment::class)->constrained()->restrictOnDelete();
            // Cancellation releases the exclusive claim while retaining the original source evidence.
            $table->foreignIdFor(Payment::class, 'active_payment_id')->nullable()->constrained('payments', indexName: 'payment_settlement_items_claim_foreign')->restrictOnDelete();
            $table->decimal('amount', 18, 4);
            $table->string('order_reference_no');
            $table->timestamp('collected_at');
            $table->unique('active_payment_id', 'payment_settlement_active_payment_unique');
            $table->unique(['payment_settlement_id', 'payment_id'], 'payment_settlement_source_unique');
        });

        Schema::create('daily_finance_payment_settlement_receipts', function (Blueprint $table): void {
            $table->id();
            $table->foreignIdFor(PaymentSettlement::class)->constrained('daily_finance_payment_settlements', indexName: 'payment_settlement_receipts_batch_foreign')->restrictOnDelete();
            $table->foreignIdFor(Branch::class)->constrained()->restrictOnDelete();
            $table->createdBy(false)->nullable(false)->constrained((new User)->getTable())->restrictOnDelete();
            $table->char('account_fingerprint', 64);
            $table->decimal('amount', 18, 4);
            $table->date('received_date');
            $table->string('reference', 120);
            $table->boolean('is_final');
            $table->text('variance_reason')->nullable();
            $table->uuid('request_key');
            $table->char('request_hash', 64);
            $table->timestamps();
            $table->unique(['payment_settlement_id', 'request_key'], 'payment_settlement_receipt_request_unique');
            $table->unique(['branch_id', 'account_fingerprint', 'reference'], 'payment_settlement_bank_reference_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('daily_finance_payment_settlement_receipts');
        Schema::dropIfExists('daily_finance_payment_settlement_items');
        Schema::dropIfExists('daily_finance_payment_settlements');
    }
};
