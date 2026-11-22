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
        Schema::table('orders', function (Blueprint $table): void {
            $table->index(
                ['created_at', 'branch_id', 'status'],
                'orders_dashboard_created_branch_status_idx',
            );
            $table->index(
                ['branch_id', 'created_at', 'status'],
                'orders_dashboard_branch_created_status_idx',
            );
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table): void {
            $table->dropIndex('orders_dashboard_created_branch_status_idx');
            $table->dropIndex('orders_dashboard_branch_created_status_idx');
        });
    }
};
