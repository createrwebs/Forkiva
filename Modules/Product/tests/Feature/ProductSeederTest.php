<?php

namespace Modules\Product\tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Branch\Models\Branch;
use Modules\Category\Database\Seeders\CategorySeeder;
use Modules\Inventory\Database\Seeders\IngredientSeeder;
use Modules\Inventory\Database\Seeders\UnitSeeder;
use Modules\Menu\Models\Menu;
use Modules\Option\Database\Seeders\OptionSeeder;
use Modules\Product\Database\Seeders\ProductSeeder;
use Modules\Product\Models\Product;
use Tests\TestCase;

class ProductSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_product_seeder_completes_with_demo_dependencies(): void
    {
        $branch = Branch::factory()->create();

        Menu::factory()->create([
            'branch_id' => $branch->id,
            'is_active' => true,
        ]);

        $this->seed([
            UnitSeeder::class,
            IngredientSeeder::class,
            OptionSeeder::class,
            CategorySeeder::class,
            ProductSeeder::class,
        ]);

        $this->assertGreaterThan(0, Product::query()->count());
    }
}
