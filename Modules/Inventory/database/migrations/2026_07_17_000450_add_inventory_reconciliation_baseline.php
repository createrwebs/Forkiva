<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('inventory_cost_balances', function (Blueprint $table): void {
            $table->timestamp('reconciliation_baseline_at')->nullable();
            $table->decimal('baseline_quantity', 18, 6)->nullable();
            $table->decimal('baseline_inventory_value', 18, 6)->nullable();
            $table->decimal('baseline_average_unit_cost', 18, 6)->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('inventory_cost_balances', function (Blueprint $table): void {
            $table->dropColumn([
                'reconciliation_baseline_at',
                'baseline_quantity',
                'baseline_inventory_value',
                'baseline_average_unit_cost',
            ]);
        });
    }
};
