<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('order_products', function (Blueprint $table): void {
            $table->unsignedSmallInteger('prep_time_minutes')->default(0)->after('product_id');
            $table->timestamp('preparation_started_at')->nullable()->after('prep_time_minutes');
        });
    }

    public function down(): void
    {
        Schema::table('order_products', function (Blueprint $table): void {
            $table->dropColumn(['prep_time_minutes', 'preparation_started_at']);
        });
    }
};
