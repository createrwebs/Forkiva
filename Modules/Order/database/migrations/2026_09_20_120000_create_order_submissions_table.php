<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Modules\Order\Models\Order;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('order_submissions', function (Blueprint $table) {
            $table->id();
            $table->string('scope', 64);
            $table->uuid('idempotency_key');
            $table->string('request_hash', 64);
            $table->foreignIdFor(Order::class)->nullable()->constrained()->nullOnDelete();
            $table->json('response')->nullable();
            $table->timestamps();
            $table->unique(['scope', 'idempotency_key']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('order_submissions');
    }
};
