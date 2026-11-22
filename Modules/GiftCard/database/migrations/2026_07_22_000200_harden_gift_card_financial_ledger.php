<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Modules\GiftCard\Enums\GiftCardStatus;
use Modules\GiftCard\Enums\GiftCardTransactionType;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('gift_cards', function (Blueprint $table) {
            $table->enum('status', GiftCardStatus::values())->default('active')->change();
        });

        Schema::table('gift_card_transactions', function (Blueprint $table) {
            $table->enum('type', GiftCardTransactionType::values())->change();
            $table->decimal('currency_rate', 18, 8)->nullable()->after('currency');
            $table->string('funding_type', 32)->nullable()->after('currency_rate');
            $table->string('tender_method', 32)->nullable()->after('funding_type');
            $table->string('financial_account_key')->nullable()->after('tender_method');
            $table->json('financial_account_snapshot')->nullable()->after('financial_account_key');
            $table->foreignId('payment_id')->nullable()->after('order_id')->constrained('payments')->nullOnDelete();
            $table->foreignId('original_transaction_id')->nullable()->after('payment_id')->constrained('gift_card_transactions')->restrictOnDelete();
            $table->foreignId('reversed_by_transaction_id')->nullable()->after('original_transaction_id')->constrained('gift_card_transactions')->restrictOnDelete();
            $table->string('idempotency_key')->nullable()->unique()->after('uuid');
            $table->date('economic_date')->nullable()->index()->after('transaction_at');
            $table->json('source_snapshot')->nullable()->after('economic_date');
            $table->timestamp('reversed_at')->nullable()->after('source_snapshot');
        });

        Schema::create('gift_card_breakage_batches', function (Blueprint $table) {
            $table->id();
            $table->branch();
            $table->string('reference_no')->unique();
            $table->string('status', 24)->default('draft')->index();
            $table->string('currency', 3);
            $table->decimal('currency_rate', 18, 8);
            $table->decimal('total_amount', 18, 4)->default(0);
            $table->date('economic_date')->index();
            $table->string('idempotency_key')->unique();
            $table->text('review_note');
            $table->json('source_snapshot')->nullable();
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->foreignId('posted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('posted_at')->nullable();
            $table->foreignId('reversal_of_id')->nullable()->constrained('gift_card_breakage_batches')->restrictOnDelete();
            $table->timestamps();
        });

        Schema::create('gift_card_breakage_batch_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('gift_card_breakage_batch_id');
            $table->foreign(
                'gift_card_breakage_batch_id',
                'gift_breakage_line_batch_fk',
            )->references('id')->on('gift_card_breakage_batches')->cascadeOnDelete();
            $table->foreignId('gift_card_id')->constrained()->restrictOnDelete();
            $table->foreignId('gift_card_transaction_id')->nullable()->constrained()->restrictOnDelete();
            $table->decimal('amount', 18, 4);
            $table->json('source_snapshot');
            $table->timestamps();
            $table->unique(['gift_card_breakage_batch_id', 'gift_card_id'], 'gift_breakage_batch_card_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('gift_card_breakage_batch_lines');
        Schema::dropIfExists('gift_card_breakage_batches');

        Schema::table('gift_card_transactions', function (Blueprint $table) {
            $table->dropForeign(['payment_id']);
            $table->dropForeign(['original_transaction_id']);
            $table->dropForeign(['reversed_by_transaction_id']);
            $table->dropUnique(['idempotency_key']);
            $table->dropIndex(['economic_date']);
            $table->dropColumn([
                'currency_rate', 'funding_type', 'tender_method', 'financial_account_key',
                'financial_account_snapshot', 'payment_id', 'original_transaction_id',
                'reversed_by_transaction_id', 'idempotency_key', 'economic_date',
                'source_snapshot', 'reversed_at',
            ]);
            $table->enum('type', ['purchase', 'redeem', 'refund', 'recharge', 'adjustment', 'expire'])->change();
        });

        Schema::table('gift_cards', function (Blueprint $table) {
            $table->enum('status', ['active', 'used', 'expired', 'disabled'])->default('active')->change();
        });
    }
};
