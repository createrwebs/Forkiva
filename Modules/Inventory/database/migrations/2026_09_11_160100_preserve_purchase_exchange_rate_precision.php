<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Keep purchase rates at the precision consumed by stock and Accounting snapshots.
     */
    public function up(): void
    {
        foreach (['purchases', 'purchase_items'] as $table) {
            Schema::table($table, fn (Blueprint $blueprint) => $blueprint->decimal('currency_rate', 22, 8)->default(1)->change());
        }
    }

    /**
     * Preserve historical rate precision when rolling back application code.
     */
    public function down(): void {}
};
