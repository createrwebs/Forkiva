<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Modules\Inventory\Models\Ingredient;
use Modules\Inventory\Models\InventoryCount;
use Modules\Inventory\Models\StockMovement;
use Modules\Inventory\Models\Unit;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('inventory_count_items', function (Blueprint $table) {
            $table->id();
            $table->branch();
            $table->foreignIdFor(InventoryCount::class)
                ->constrained(table: 'inventory_counts', indexName: 'inv_count_items_count_fk')
                ->cascadeOnDelete();
            $table->foreignIdFor(Ingredient::class)
                ->constrained(indexName: 'inv_count_items_ingredient_fk')
                ->restrictOnDelete();
            $table->foreignIdFor(Unit::class)
                ->constrained(indexName: 'inv_count_items_unit_fk')
                ->restrictOnDelete();
            $table->decimal('expected_quantity', 18, 6);
            $table->decimal('counted_quantity', 18, 6)->unsigned();
            $table->decimal('variance_quantity', 18, 6);
            $table->foreignIdFor(StockMovement::class, 'snapshot_movement_id')
                ->nullable()
                ->constrained(table: 'stock_movements', indexName: 'inv_count_items_snapshot_mv_fk')
                ->nullOnDelete();
            $table->foreignIdFor(StockMovement::class, 'generated_movement_id')
                ->nullable()
                ->constrained(table: 'stock_movements', indexName: 'inv_count_items_generated_mv_fk')
                ->nullOnDelete();
            $table->timestamps();

            $table->unique(['inventory_count_id', 'ingredient_id'], 'inv_count_items_count_ingredient_unique');
            $table->index(['branch_id', 'ingredient_id'], 'inv_count_items_branch_ingredient_idx');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('inventory_count_items');
    }
};
