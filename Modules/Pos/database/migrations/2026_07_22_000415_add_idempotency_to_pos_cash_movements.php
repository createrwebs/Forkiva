<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pos_cash_movements', function (Blueprint $table): void {
            $table->string('idempotency_key')->nullable()->unique()->after('notes');
        });
    }

    public function down(): void
    {
        Schema::table('pos_cash_movements', function (Blueprint $table): void {
            $table->dropUnique(['idempotency_key']);
            $table->dropColumn('idempotency_key');
        });
    }
};
