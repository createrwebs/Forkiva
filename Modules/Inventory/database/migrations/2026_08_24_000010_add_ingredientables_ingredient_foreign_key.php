<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (! Schema::hasTable('ingredients')) {
            return;
        }

        if (Schema::hasTable('ingredientables') && $this->ingredientForeignKeyName() === null) {
            Schema::table('ingredientables', function (Blueprint $table): void {
                $table->foreign('ingredient_id')
                    ->references('id')
                    ->on('ingredients')
                    ->cascadeOnDelete();
            });
        }

        if (Schema::hasTable('products') && $this->producedIngredientForeignKeyName() === null) {
            Schema::table('products', function (Blueprint $table): void {
                $table->foreign('produced_ingredient_id')
                    ->references('id')
                    ->on('ingredients')
                    ->nullOnDelete();
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasTable('products')) {
            $foreignKey = $this->producedIngredientForeignKeyName();

            if ($foreignKey !== null) {
                Schema::table('products', function (Blueprint $table) use ($foreignKey): void {
                    $table->dropForeign($foreignKey);
                });
            }
        }

        if (Schema::hasTable('ingredientables')) {
            $foreignKey = $this->ingredientForeignKeyName();

            if ($foreignKey !== null) {
                Schema::table('ingredientables', function (Blueprint $table) use ($foreignKey): void {
                    $table->dropForeign($foreignKey);
                });
            }
        }
    }

    /**
     * Find the ingredient foreign key, including constraints created by older installs.
     */
    private function ingredientForeignKeyName(): ?string
    {
        foreach (Schema::getForeignKeys('ingredientables') as $foreignKey) {
            if (($foreignKey['columns'] ?? []) === ['ingredient_id']) {
                return $foreignKey['name'];
            }
        }

        return null;
    }

    /**
     * Find the product production-output ingredient foreign key.
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
