<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Modules\Inventory\Enums\InventoryStockSyncOperation;
use Modules\Inventory\Enums\InventoryStockSyncStatus;
use Modules\Order\Models\Order;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('inventory_stock_sync_jobs', function (Blueprint $table) {
            $table->id();
            $table->branch(false);
            $table->foreignIdFor(Order::class);
            $table->foreign('order_id', 'inv_stock_sync_order_fk')
                ->references('id')
                ->on('orders')
                ->cascadeOnDelete();
            $table->string('operation', 24)->default(InventoryStockSyncOperation::Deduct->value);
            $table->string('status', 32)->default(InventoryStockSyncStatus::PendingStock->value);
            $table->string('idempotency_key', 191)->unique('inv_stock_sync_idem_unique');
            $table->unsignedSmallInteger('attempts')->default(0);
            $table->json('movement_ids')->nullable();
            $table->json('payload')->nullable();
            $table->text('last_error')->nullable();
            $table->timestamp('processing_started_at')->nullable();
            $table->timestamp('last_attempt_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();

            $table->index(['status', 'updated_at'], 'inv_stock_sync_status_updated_idx');
            $table->index(['branch_id', 'order_id'], 'inv_stock_sync_branch_order_idx');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('inventory_stock_sync_jobs');
    }
};
