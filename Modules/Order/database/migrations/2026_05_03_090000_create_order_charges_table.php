<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Modules\Order\Models\Order;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('order_charges', function (Blueprint $table) {
            $table->id();
            $table->foreignIdFor(Order::class)->constrained()->cascadeOnDelete();
            $table->string('code');
            $table->json('name');
            $table->string('currency', 3);
            $table->decimal('currency_rate', 18, 4)->default(1);
            $table->decimal('unit_amount', 18, 4);
            $table->decimal('quantity', 18, 4)->default(1);
            $table->decimal('amount', 18, 4);
            $table->json('meta')->nullable();
            $table->timestamps();

            $table->unique(['order_id', 'code']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('order_charges');
    }
};
