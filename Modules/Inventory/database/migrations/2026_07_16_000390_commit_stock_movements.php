<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        DB::table('stock_movements')
            ->where('quantity', '<', 0)
            ->update([
                'type' => DB::raw("CASE type
                    WHEN 'in' THEN 'out'
                    WHEN 'out' THEN 'in'
                    WHEN 'adjust_add' THEN 'adjust_subtract'
                    WHEN 'adjust_subtract' THEN 'adjust_add'
                    WHEN 'transfer_in' THEN 'transfer_out'
                    WHEN 'transfer_out' THEN 'transfer_in'
                    ELSE type
                END"),
                'quantity' => DB::raw('ABS(quantity)'),
            ]);

        Schema::table('stock_movements', function (Blueprint $table) {
            $table->decimal('quantity', 18, 6)->unsigned()->change();
            $table->foreignId('reversal_of_id')->nullable()->after('id');
            $table->timestamp('occurred_at')->nullable()->after('currency');
            $table->timestamp('committed_at')->nullable()->after('currency');

            $table->foreign('reversal_of_id', 'stock_mov_reversal_fk')
                ->references('id')
                ->on('stock_movements')
                ->nullOnDelete();
            $table->index('committed_at', 'stock_mov_committed_idx');
        });

        DB::table('stock_movements')
            ->whereNull('committed_at')
            ->update([
                'occurred_at' => DB::raw('created_at'),
                'committed_at' => DB::raw('created_at'),
            ]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::getConnection()->getDriverName() === 'sqlite') {
            Schema::table('stock_movements', function (Blueprint $table): void {
                $table->dropForeign(['reversal_of_id']);
                $table->dropIndex('stock_mov_committed_idx');
                $table->dropColumn(['reversal_of_id', 'occurred_at', 'committed_at']);
                $table->decimal('quantity', 18, 4)->unsigned()->change();
            });

            return;
        }

        Schema::table('stock_movements', function (Blueprint $table) {
            $table->dropForeign('stock_mov_reversal_fk');
            $table->dropIndex('stock_mov_committed_idx');
            $table->dropColumn(['reversal_of_id', 'occurred_at', 'committed_at']);
            $table->decimal('quantity', 18, 4)->unsigned()->change();
        });
    }
};
