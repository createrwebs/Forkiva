<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Modules\Inventory\Models\Ingredient;
use Modules\Inventory\Models\Unit;
use Modules\Order\Models\Order;
use Modules\Order\Models\OrderProduct;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        $duplicateReversal = DB::table('stock_movements')
            ->select('reversal_of_id')
            ->whereNotNull('reversal_of_id')
            ->groupBy('reversal_of_id')
            ->havingRaw('COUNT(*) > 1')
            ->value('reversal_of_id');

        if ($duplicateReversal !== null) {
            throw new RuntimeException(
                "Stock movement $duplicateReversal has multiple reversals. Repair the movement history before running this migration.",
            );
        }

        Schema::table('orders', function (Blueprint $table): void {
            $table->timestamp('stock_bom_snapshotted_at')->nullable()->after('is_stock_deducted');
            $table->timestamp('stock_effective_at')->nullable()->after('stock_bom_snapshotted_at');
        });

        Schema::create('order_product_ingredient_snapshots', function (Blueprint $table): void {
            $table->id();
            $table->foreignIdFor(Order::class);
            $table->foreignIdFor(OrderProduct::class);
            $table->foreignIdFor(Ingredient::class);
            $table->foreignIdFor(Unit::class);
            $table->unsignedBigInteger('branch_id');
            $table->decimal('net_quantity_per_product', 18, 6)->unsigned();
            $table->decimal('gross_quantity_per_product', 18, 6)->unsigned();
            $table->decimal('order_product_quantity', 18, 6)->unsigned();
            $table->decimal('quantity', 18, 6)->unsigned();
            $table->decimal('loss_pct', 8, 4)->unsigned()->default(0);
            $table->timestamps();

            $table->foreign('order_id', 'order_ing_snap_order_fk')
                ->references('id')
                ->on('orders')
                ->cascadeOnDelete();
            $table->foreign('order_product_id', 'order_ing_snap_product_fk')
                ->references('id')
                ->on('order_products')
                ->cascadeOnDelete();
            $table->foreign('ingredient_id', 'order_ing_snap_ingredient_fk')
                ->references('id')
                ->on('ingredients')
                ->restrictOnDelete();
            $table->foreign('unit_id', 'order_ing_snap_unit_fk')
                ->references('id')
                ->on('units')
                ->restrictOnDelete();
            $table->unique(
                ['order_product_id', 'ingredient_id'],
                'order_ing_snap_product_ingredient_unique',
            );
            $table->index(['branch_id', 'order_id'], 'order_ing_snap_branch_order_idx');
        });

        Schema::table('stock_movements', function (Blueprint $table): void {
            $table->unique('reversal_of_id', 'stock_mov_reversal_unique');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('stock_movements', function (Blueprint $table): void {
            $table->dropUnique('stock_mov_reversal_unique');
        });

        Schema::dropIfExists('order_product_ingredient_snapshots');

        Schema::table('orders', function (Blueprint $table): void {
            $table->dropColumn(['stock_bom_snapshotted_at', 'stock_effective_at']);
        });
    }
};
