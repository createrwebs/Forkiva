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
        Schema::table('order_product_ingredient_snapshots', function (Blueprint $table): void {
            $table->boolean('is_returnable')->default(false)->after('loss_pct');
        });

        DB::table('order_product_ingredient_snapshots')
            ->select(['id', 'ingredient_id'])
            ->orderBy('id')
            ->chunkById(500, function ($snapshots): void {
                $returnableByIngredient = DB::table('ingredients')
                    ->whereIn('id', $snapshots->pluck('ingredient_id')->unique()->all())
                    ->pluck('is_returnable', 'id');

                foreach ($snapshots as $snapshot) {
                    DB::table('order_product_ingredient_snapshots')
                        ->where('id', $snapshot->id)
                        ->update([
                            'is_returnable' => (bool) $returnableByIngredient->get($snapshot->ingredient_id, false),
                        ]);
                }
            });

        Schema::table('orders', function (Blueprint $table): void {
            $table->decimal('gross_profit', 18, 4)->default(0)->after('cost_price');
        });
        Schema::table('order_products', function (Blueprint $table): void {
            $table->decimal('gross_profit', 18, 4)->default(0)->after('cost_price');
        });

        // Older installations may still have this deprecated alias available
        // when this migration runs. Fresh module-ordered installs do not.
        if (Schema::hasColumn('orders', 'revenue')) {
            DB::table('orders')->update(['gross_profit' => DB::raw('revenue')]);
        }

        if (Schema::hasColumn('order_products', 'revenue')) {
            DB::table('order_products')->update(['gross_profit' => DB::raw('revenue')]);
        }

        Schema::table('stock_movements', function (Blueprint $table): void {
            $table->boolean('legacy_cost_snapshot_missing')->default(false)->after('currency');
        });

        DB::table('stock_movements')
            ->where(function ($query): void {
                $query->whereNull('unit_cost')->orWhereNull('currency');
            })
            ->update(['legacy_cost_snapshot_missing' => true]);

        if (DB::getDriverName() === 'mysql') {
            DB::statement(
                'ALTER TABLE stock_movements
                ADD CONSTRAINT stock_mov_cost_snapshot_check
                CHECK (
                    legacy_cost_snapshot_missing = 1
                    OR (unit_cost IS NOT NULL AND currency IS NOT NULL)
                )',
            );
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (DB::getDriverName() === 'mysql') {
            DB::statement(
                'ALTER TABLE stock_movements DROP CHECK stock_mov_cost_snapshot_check',
            );
        }

        Schema::table('stock_movements', function (Blueprint $table): void {
            $table->dropColumn('legacy_cost_snapshot_missing');
        });
        Schema::table('order_products', function (Blueprint $table): void {
            $table->dropColumn('gross_profit');
        });
        Schema::table('orders', function (Blueprint $table): void {
            $table->dropColumn('gross_profit');
        });
        Schema::table('order_product_ingredient_snapshots', function (Blueprint $table): void {
            $table->dropColumn('is_returnable');
        });
    }
};
