<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('order_products', function (Blueprint $table): void {
            $table->timestamp('prepared_at')->nullable()->after('preparation_started_at');
        });

        Schema::table('orders', function (Blueprint $table): void {
            $table->timestamp('preparation_started_at')->nullable()->after('scheduled_at');
            $table->timestamp('prepared_at')->nullable()->after('preparation_started_at');
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table): void {
            $table->dropColumn(['preparation_started_at', 'prepared_at']);
        });

        Schema::table('order_products', function (Blueprint $table): void {
            $table->dropColumn('prepared_at');
        });
    }
};
