<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Modules\Order\Models\Order;
use Modules\SeatingPlan\Models\Table;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('order_source_requests', function (Blueprint $table) {
            $table->id();
            $table->string('source', 50)->index();
            $table->foreignIdFor(Table::class)->nullable()->constrained()->nullOnDelete();
            $table->uuid('cart_uuid')->nullable()->index();
            $table->string('idempotency_key', 150);
            $table->foreignIdFor(Order::class)->nullable()->constrained()->nullOnDelete();
            $table->string('request_hash', 64);
            $table->unsignedSmallInteger('response_status')->nullable();
            $table->json('response_body')->nullable();
            $table->timestamps();

            $table->unique(['source', 'table_id', 'cart_uuid', 'idempotency_key'], 'order_source_requests_source_table_cart_key_unique');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('order_source_requests');
    }
};
