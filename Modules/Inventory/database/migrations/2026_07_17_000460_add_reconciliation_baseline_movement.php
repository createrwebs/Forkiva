<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('inventory_cost_balances', function (Blueprint $table): void {
            $table->unsignedBigInteger('reconciliation_baseline_movement_id')->nullable();
        });

        DB::table('inventory_cost_balances')
            ->whereNotNull('reconciliation_baseline_at')
            ->orderBy('id')
            ->eachById(function (object $balance): void {
                $movementId = DB::table('stock_movements')
                    ->where('branch_id', $balance->branch_id)
                    ->where('ingredient_id', $balance->ingredient_id)
                    ->max('id');

                DB::table('inventory_cost_balances')
                    ->where('id', $balance->id)
                    ->update(['reconciliation_baseline_movement_id' => $movementId]);
            });
    }

    public function down(): void
    {
        Schema::table('inventory_cost_balances', function (Blueprint $table): void {
            $table->dropColumn('reconciliation_baseline_movement_id');
        });
    }
};
