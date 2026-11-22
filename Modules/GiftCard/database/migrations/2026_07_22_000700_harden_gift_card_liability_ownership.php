<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $duplicatePaymentIds = DB::table('gift_card_transactions')
            ->whereNotNull('payment_id')
            ->groupBy('payment_id')
            ->havingRaw('COUNT(*) > 1')
            ->pluck('payment_id');
        if ($duplicatePaymentIds->isNotEmpty()) {
            throw new RuntimeException(
                'Gift Card migration blocked: duplicate payment-linked transactions must be reviewed first (payment IDs: '
                .$duplicatePaymentIds->implode(', ').').',
            );
        }

        Schema::table('gift_cards', function (Blueprint $table): void {
            $table->foreignId('liability_branch_id')
                ->nullable()
                ->after('branch_id')
                ->constrained('branches')
                ->restrictOnDelete();
            $table->index(['liability_branch_id', 'currency'], 'gift_cards_liability_branch_currency_idx');
        });

        DB::table('gift_cards')
            ->whereNotNull('branch_id')
            ->whereNull('liability_branch_id')
            ->update(['liability_branch_id' => DB::raw('branch_id')]);

        Schema::table('gift_card_transactions', function (Blueprint $table): void {
            $table->char('request_fingerprint', 64)->nullable()->after('idempotency_key')->index();
            $table->string('currency_rate_source', 64)->nullable()->after('currency_rate');
            $table->string('currency_rate_reviewed_by')->nullable()->after('currency_rate_source');
            $table->timestamp('currency_rate_reviewed_at')->nullable()->after('currency_rate_reviewed_by');
            $table->text('currency_rate_review_note')->nullable()->after('currency_rate_reviewed_at');
            $table->unique('payment_id', 'gift_card_transactions_payment_unique');
        });

        Schema::table('gift_card_transactions', function (Blueprint $table): void {
            $table->dropForeign(['gift_card_id']);
            $table->foreign('gift_card_id')->references('id')->on('gift_cards')->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('gift_card_transactions', function (Blueprint $table): void {
            $table->dropForeign(['gift_card_id']);
            $table->foreign('gift_card_id')->references('id')->on('gift_cards')->cascadeOnDelete();
            $table->dropUnique('gift_card_transactions_payment_unique');
            $table->dropIndex(['request_fingerprint']);
            $table->dropColumn([
                'request_fingerprint',
                'currency_rate_source',
                'currency_rate_reviewed_by',
                'currency_rate_reviewed_at',
                'currency_rate_review_note',
            ]);
        });

        Schema::table('gift_cards', function (Blueprint $table): void {
            $table->dropIndex('gift_cards_liability_branch_currency_idx');
            $table->dropConstrainedForeignId('liability_branch_id');
        });
    }
};
