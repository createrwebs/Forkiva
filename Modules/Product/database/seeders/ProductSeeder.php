<?php

namespace Modules\Product\Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Collection;
use Modules\Category\Models\Category;
use Modules\Inventory\Models\Ingredient;
use Modules\Menu\Models\Menu;
use Modules\Option\Models\Option;
use Modules\Product\Enums\IngredientOperation;
use Modules\Product\Models\Ingredientable;
use Modules\Product\Models\Product;
use Modules\Product\Services\ProductOption\ProductOptionServiceInterface;
use Modules\Support\Money;

class ProductSeeder extends Seeder
{
    /**
     * @var array<int, Collection<int, int>>
     */
    private array $ingredientIdsByBranch = [];

    /**
     * @var Collection<int, array>
     */
    private Collection $optionTemplates;

    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $data = require 'demo_products.php';
        $this->optionTemplates = $this->getOptionTemplates();

        $categories = [];
        foreach (Menu::all() as $menu) {
            foreach ($data as $categoryName => $products) {
                if (! isset($categories[$menu->id][$categoryName])) {
                    $category = Category::query()
                        ->where('menu_id', $menu->id)
                        ->withoutGlobalActive()
                        ->whereLikeTranslation('name', $categoryName)
                        ->first();
                    if (! is_null($category)) {
                        $categories[$menu->id][$categoryName] = $category;
                    } else {
                        continue;
                    }
                } else {
                    $category = $categories[$menu->id][$categoryName];
                }
                foreach ($products as $data) {
                    /** @var Product $product */
                    $product = Product::factory()
                        ->create([
                            'name' => $data['name'],
                            'description' => $data['description'],
                            'menu_id' => $menu->id,
                        ]);

                    $product->categories()->attach([$category->id]);
                    $this->createIngredientables($product, $menu->branch_id);
                    $this->createOptions($product);
                }
            }
        }
    }

    /**
     * Create ingredientables
     */
    private function createIngredientables(Product $product, int $branchId): void
    {
        $ingredients = $this->getIngredientIds($branchId);

        if ($ingredients->isEmpty()) {
            return;
        }

        $count = min(rand(1, 10), $ingredients->count());

        foreach ($ingredients->random($count) as $ingredientId) {
            Ingredientable::factory()
                ->create([
                    'ingredient_id' => $ingredientId,
                    'ingredientable_id' => $product->id,
                    'ingredientable_type' => $product->getMorphClass(),
                    'priority' => 10,
                    'branch_id' => $branchId,
                    'operation' => IngredientOperation::Add,
                ]);
        }
    }

    /**
     * Create options
     */
    private function createOptions(Product $product): void
    {
        if ($this->optionTemplates->isEmpty()) {
            return;
        }

        $options = $this->optionTemplates
            ->random(min(rand(1, 5), $this->optionTemplates->count()))
            ->values()
            ->toArray();

        $productOptionService = app(ProductOptionServiceInterface::class);
        $productOptionService->syncOptions($product, $options);
    }

    /**
     * @return Collection<int, int>
     */
    private function getIngredientIds(int $branchId): Collection
    {
        if (! isset($this->ingredientIdsByBranch[$branchId])) {
            $this->ingredientIdsByBranch[$branchId] = Ingredient::query()
                ->where('branch_id', $branchId)
                ->pluck('id');
        }

        return $this->ingredientIdsByBranch[$branchId];
    }

    /**
     * @return Collection<int, array>
     */
    private function getOptionTemplates(): Collection
    {
        return Option::query()
            ->with('values')
            ->get()
            ->map(function ($option) {
                return [
                    'name' => $option->getTranslations('name'),
                    'type' => $option->type->value,
                    'is_required' => $option->is_required,
                    'values' => $option->values->map(function ($value) {
                        return [
                            'label' => $value->getTranslations('label'),
                            'price' => $value->price instanceof Money ? $value->price->amount() : $value->price,
                            'price_type' => $value->price_type,
                        ];
                    })
                        ->toArray(),
                ];
            });
    }
}
