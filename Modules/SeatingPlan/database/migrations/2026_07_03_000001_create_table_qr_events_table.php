<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Modules\SeatingPlan\Models\Table;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('table_qr_events', function (Blueprint $table) {
            $table->id();
            $table->createdBy();
            $table->foreignIdFor(Table::class)->constrained()->cascadeOnDelete();
            $table->string('event_type', 50)->index();
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->uuid('cart_uuid')->nullable()->index();
            // Keep this as a plain reference to avoid cross-module migration ordering failures.
            $table->unsignedBigInteger('order_id')->nullable()->index();
            $table->timestamp('created_at')->nullable()->index();

            $table->index(['table_id', 'event_type', 'created_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('table_qr_events');
    }
};
