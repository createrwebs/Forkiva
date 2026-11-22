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
            $table->uuid('dispatch_token')->nullable()->after('dispatched_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('inventory_stock_sync_jobs', function (Blueprint $table) {
            $table->dropColumn('dispatch_token');
        });
    }
};
