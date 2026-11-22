<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Modules\Product\Enums\ProductInventoryConsumptionMode;

return new class extends Migration
{
    /**
     * Add the mutually exclusive stock-consumption configuration.
     */
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->enum('inventory_consumption_mode', ProductInventoryConsumptionMode::values())
                ->default(ProductInventoryConsumptionMode::DirectRecipe->value)
                ->after('sku');
            // Inventory migrations run after Product migrations. The Inventory
            // module adds this cross-module constraint once ingredients exists.
            $table->unsignedBigInteger('produced_ingredient_id')
                ->nullable()
                ->after('inventory_consumption_mode');
            $table->decimal('produced_ingredient_quantity', 18, 6)
                ->nullable()
                ->after('produced_ingredient_id');
        });
    }

    /**
     * Remove the product stock-consumption configuration.
     */
    public function down(): void
    {
        $foreignKey = $this->producedIngredientForeignKeyName();

        if ($foreignKey !== null) {
            Schema::table('products', function (Blueprint $table) use ($foreignKey): void {
                $table->dropForeign($foreignKey);
            });
        }

        Schema::table('products', function (Blueprint $table) {
            $table->dropColumn('produced_ingredient_id');
            $table->dropColumn(['inventory_consumption_mode', 'produced_ingredient_quantity']);
        });
    }

    /**
     * Find the cross-module foreign key when the Inventory repair migration ran.
     */
    private function producedIngredientForeignKeyName(): ?string
    {
        foreach (Schema::getForeignKeys('products') as $foreignKey) {
            if (($foreignKey['columns'] ?? []) === ['produced_ingredient_id']) {
                return $foreignKey['name'];
            }
        }

        return null;
    }
};
