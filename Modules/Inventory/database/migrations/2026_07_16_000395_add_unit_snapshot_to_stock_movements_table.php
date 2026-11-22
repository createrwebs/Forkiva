<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Modules\Inventory\Models\Unit;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('stock_movements', function (Blueprint $table): void {
            $table->foreignIdFor(Unit::class, 'unit_id')->nullable()->after('ingredient_id');
            $table->foreign('unit_id', 'stock_mov_unit_fk')
                ->references('id')
                ->on('units')
                ->restrictOnDelete();
            $table->index('unit_id', 'stock_mov_unit_idx');
        });

        DB::table('stock_movements')
            ->whereNull('unit_id')
            ->update([
                'unit_id' => DB::raw(
                    '(SELECT ingredients.unit_id FROM ingredients WHERE ingredients.id = stock_movements.ingredient_id)'
                ),
            ]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::getConnection()->getDriverName() === 'sqlite') {
            Schema::table('stock_movements', function (Blueprint $table): void {
                $table->dropForeign(['unit_id']);
                $table->dropIndex('stock_mov_unit_idx');
                $table->dropColumn('unit_id');
            });

            return;
        }

        Schema::table('stock_movements', function (Blueprint $table): void {
            $table->dropForeign('stock_mov_unit_fk');
            $table->dropIndex('stock_mov_unit_idx');
            $table->dropColumn('unit_id');
        });
    }
};
