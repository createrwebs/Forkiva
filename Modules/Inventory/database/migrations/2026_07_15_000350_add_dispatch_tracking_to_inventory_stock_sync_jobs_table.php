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
        Schema::table('inventory_stock_sync_jobs', function (Blueprint $table) {
            $table->timestamp('dispatched_at')->nullable()->after('last_error');
            $table->index(['status', 'dispatched_at'], 'inv_stock_sync_dispatch_idx');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('inventory_stock_sync_jobs', function (Blueprint $table) {
            $table->dropIndex('inv_stock_sync_dispatch_idx');
            $table->dropColumn('dispatched_at');
        });
    }
};
