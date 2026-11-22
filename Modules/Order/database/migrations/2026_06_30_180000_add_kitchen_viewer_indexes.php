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
        Schema::table('orders', function (Blueprint $table) {
            $table->index(['branch_id', 'status', 'updated_at'], 'orders_kitchen_branch_status_updated_idx');
        });

        Schema::table('order_status_logs', function (Blueprint $table) {
            $table->index(['order_id', 'status', 'created_at'], 'order_status_logs_order_status_created_idx');
        });

        Schema::table('order_products', function (Blueprint $table) {
            $table->index(['order_id', 'updated_at'], 'order_products_order_updated_idx');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('order_products', function (Blueprint $table) {
            $table->dropIndex('order_products_order_updated_idx');
        });

        Schema::table('order_status_logs', function (Blueprint $table) {
            $table->dropIndex('order_status_logs_order_status_created_idx');
        });

        Schema::table('orders', function (Blueprint $table) {
            $table->dropIndex('orders_kitchen_branch_status_updated_idx');
        });
    }
};
