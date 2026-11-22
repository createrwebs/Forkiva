<?php

namespace Modules\Inventory\Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Modules\Branch\Models\Branch;
use Modules\Inventory\Models\Ingredient;
use Modules\Inventory\Models\Unit;
use Modules\Inventory\Services\StockMovement\StockMovementServiceInterface;
use Modules\Inventory\Services\StockSync\InvalidStockSyncConfigurationException;
use Modules\Inventory\Services\StockSync\StockSyncService;
use Modules\Product\Enums\IngredientOperation;
use Modules\Product\Enums\ProductInventoryConsumptionMode;
use Modules\Product\Models\Ingredientable;
use Modules\Product\Models\Product;
use Tests\TestCase;

class ProductInventoryConsumptionTest extends TestCase
{
    use RefreshDatabase;

    public function test_production_output_mode_resolves_only_the_prepared_item(): void
    {
        $branch = Branch::factory()->create();
        $prepared = $this->ingredient($branch);
        $raw = $this->ingredient($branch);
        $product = new Product;
        $product->forceFill([
            'inventory_consumption_mode' => ProductInventoryConsumptionMode::ProductionOutput,
            'produced_ingredient_id' => $prepared->id,
            'produced_ingredient_quantity' => 0.25,
        ]);
        $product->setRelation('producedIngredient', $prepared);
        $product->setRelation('ingredients', collect([$this->line($branch, $raw, 3)]));

        $lines = $this->service()->resolveProductBom($product, collect(), $branch->id);

        $this->assertCount(1, $lines);
        $this->assertSame($prepared->id, (int) $lines->first()->ingredient_id);
        $this->assertSame('0.250000', $lines->first()->quantity);
        $this->assertFalse($lines->contains('ingredient_id', $raw->id));
    }

    public function test_direct_recipe_mode_remains_backward_compatible(): void
    {
        $branch = Branch::factory()->create();
        $raw = $this->ingredient($branch);
        $product = new Product;
        $product->forceFill([
            'inventory_consumption_mode' => ProductInventoryConsumptionMode::DirectRecipe,
        ]);
        $product->setRelation('ingredients', collect([$this->line($branch, $raw, 2)]));

        $lines = $this->service()->resolveProductBom($product, collect(), $branch->id);

        $this->assertCount(1, $lines);
        $this->assertSame($raw->id, (int) $lines->first()->ingredient_id);
        $this->assertEqualsWithDelta(2, (float) $lines->first()->quantity, 0.000001);
    }

    public function test_production_output_mode_rejects_an_invalid_mapping(): void
    {
        $branch = Branch::factory()->create();
        $product = new Product;
        $product->forceFill([
            'inventory_consumption_mode' => ProductInventoryConsumptionMode::ProductionOutput,
            'produced_ingredient_quantity' => 1,
        ]);
        $product->setRelation('producedIngredient', null);
        $product->setRelation('ingredients', collect());

        $this->expectException(InvalidStockSyncConfigurationException::class);

        $this->service()->resolveProductBom($product, collect(), $branch->id);
    }

    private function service(): StockSyncService
    {
        return new StockSyncService(Mockery::mock(StockMovementServiceInterface::class));
    }

    private function ingredient(Branch $branch): Ingredient
    {
        $unit = Unit::query()->create([
            'name' => ['en' => 'Gram', 'ar' => 'غرام'],
            'symbol' => ['en' => 'g', 'ar' => 'غ'],
            'type' => 'mass',
        ]);

        return Ingredient::query()->create([
            'branch_id' => $branch->id,
            'unit_id' => $unit->id,
            'name' => ['en' => 'Inventory item', 'ar' => 'صنف مخزون'],
            'cost_per_unit' => 1,
            'alert_quantity' => 0,
            'current_stock' => 10,
            'is_returnable' => false,
        ]);
    }

    private function line(Branch $branch, Ingredient $ingredient, float $quantity): Ingredientable
    {
        $line = (new Ingredientable)->forceFill([
            'branch_id' => $branch->id,
            'ingredient_id' => $ingredient->id,
            'quantity' => $quantity,
            'loss_pct' => 0,
            'operation' => IngredientOperation::Add,
        ]);
        $line->setRelation('ingredient', $ingredient);

        return $line;
    }
}
