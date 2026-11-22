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
        Schema::table('pos_cash_movements', function (Blueprint $table): void {
            $table->index(
                ['occurred_at', 'branch_id', 'deleted_at', 'direction'],
                'cash_movements_dashboard_occurred_branch_direction_idx',
            );
            $table->index(
                ['branch_id', 'occurred_at', 'deleted_at', 'direction'],
                'cash_movements_dashboard_branch_occurred_direction_idx',
            );
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('pos_cash_movements', function (Blueprint $table): void {
            $table->dropIndex('cash_movements_dashboard_occurred_branch_direction_idx');
            $table->dropIndex('cash_movements_dashboard_branch_occurred_direction_idx');
        });
    }
};
