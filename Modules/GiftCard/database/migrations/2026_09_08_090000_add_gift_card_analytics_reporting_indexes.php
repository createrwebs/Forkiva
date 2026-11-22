<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('gift_cards', function (Blueprint $table): void {
            $table->index(
                ['created_at', 'branch_id'],
                'gift_cards_analytics_created_branch_idx',
            );
            $table->index(
                ['branch_id', 'created_at'],
                'gift_cards_analytics_branch_created_idx',
            );
            $table->index(
                ['branch_id', 'status', 'expiry_date'],
                'gift_cards_analytics_branch_status_expiry_idx',
            );
            $table->index(
                ['branch_id', 'currency'],
                'gift_cards_analytics_branch_currency_idx',
            );
        });

        Schema::table('gift_card_transactions', function (Blueprint $table): void {
            $table->index(
                ['transaction_at', 'branch_id', 'type'],
                'gift_card_tx_analytics_at_branch_type_idx',
            );
            $table->index(
                ['branch_id', 'transaction_at', 'type'],
                'gift_card_tx_analytics_branch_at_type_idx',
            );
        });

        Schema::table('gift_card_batches', function (Blueprint $table): void {
            $table->index(
                ['created_at', 'branch_id'],
                'gift_card_batches_analytics_created_branch_idx',
            );
            $table->index(
                ['branch_id', 'created_at'],
                'gift_card_batches_analytics_branch_created_idx',
            );
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('gift_card_batches', function (Blueprint $table): void {
            $table->dropIndex('gift_card_batches_analytics_created_branch_idx');
            $table->dropIndex('gift_card_batches_analytics_branch_created_idx');
        });

        Schema::table('gift_card_transactions', function (Blueprint $table): void {
            $table->dropIndex('gift_card_tx_analytics_at_branch_type_idx');
            $table->dropIndex('gift_card_tx_analytics_branch_at_type_idx');
        });

        Schema::table('gift_cards', function (Blueprint $table): void {
            $table->dropIndex('gift_cards_analytics_created_branch_idx');
            $table->dropIndex('gift_cards_analytics_branch_created_idx');
            $table->dropIndex('gift_cards_analytics_branch_status_expiry_idx');
            $table->dropIndex('gift_cards_analytics_branch_currency_idx');
        });
    }
};
