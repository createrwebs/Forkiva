<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Modules\Inventory\Models\Ingredient;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('inventory_cost_balances', function (Blueprint $table) {
            $table->id();
            $table->branch(false);
            $table->foreignIdFor(Ingredient::class)
                ->constrained()
                ->cascadeOnDelete();
            $table->decimal('quantity_on_hand', 18, 6)->default(0);
            $table->decimal('inventory_value', 18, 6)->default(0);
            $table->decimal('average_unit_cost', 18, 6)->nullable();
            $table->string('currency', 3);
            $table->boolean('requires_remediation')->default(false);
            $table->string('remediation_reason')->nullable();
            $table->timestamps();

            $table->unique(
                ['branch_id', 'ingredient_id'],
                'inv_cost_bal_branch_ing_uq',
            );
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('inventory_cost_balances');
    }
};
