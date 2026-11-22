<?php

namespace Modules\Pos\Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Branch\Models\Branch;
use Modules\Category\Models\Category;
use Modules\Menu\Models\Menu;
use Modules\Pos\Services\PosViewer\PosViewerService;
use Modules\Product\Models\Product;
use Tests\TestCase;

class PosProductVisibilityTest extends TestCase
{
    use RefreshDatabase;

    public function test_all_products_include_active_uncategorized_products_and_exclude_hidden_categories(): void
    {
        [, $menu, $activeCategory, $inactiveCategory] = $this->createMenu();

        $categorized = $this->createProduct($menu, $activeCategory);
        $uncategorized = $this->createProduct($menu);
        $hidden = $this->createProduct($menu, $inactiveCategory);

        $products = (new PosViewerService)->getAllProducts($menu->id)->collection;

        $this->assertEqualsCanonicalizing(
            [$categorized->id, $uncategorized->id],
            $products->pluck('id')->all()
        );
        $this->assertNotContains($hidden->id, $products->pluck('id')->all());
    }

    public function test_category_filter_and_pagination_return_every_matching_product(): void
    {
        [, $menu, $activeCategory] = $this->createMenu();

        Product::factory()
            ->count(30)
            ->create([
                'menu_id' => $menu->id,
                'is_active' => true,
            ])
            ->each(fn (Product $product) => $product->categories()->attach($activeCategory));

        request()->merge(['per_page' => 24, 'page' => 1]);
        $firstPage = (new PosViewerService)->getProducts($menu->id, [
            'category_ids' => [$activeCategory->id],
        ]);

        request()->merge(['page' => 2]);
        $secondPage = (new PosViewerService)->getProducts($menu->id, [
            'category_ids' => [$activeCategory->id],
        ]);

        $this->assertSame(30, $firstPage->total());
        $this->assertCount(24, $firstPage->items());
        $this->assertCount(6, $secondPage->items());
        $this->assertEmpty(
            collect($firstPage->items())->pluck('id')->intersect(collect($secondPage->items())->pluck('id'))
        );
    }

    /**
     * @return array{Branch, Menu, Category, Category}
     */
    private function createMenu(): array
    {
        $branch = Branch::factory()->create();
        $menu = Menu::factory()->create([
            'branch_id' => $branch->id,
            'is_active' => true,
        ]);
        $activeCategory = Category::query()->create([
            'name' => ['en' => 'Visible'],
            'menu_id' => $menu->id,
            'is_active' => true,
        ]);
        $inactiveCategory = Category::query()->create([
            'name' => ['en' => 'Hidden'],
            'menu_id' => $menu->id,
            'is_active' => false,
        ]);

        return [$branch, $menu, $activeCategory, $inactiveCategory];
    }

    private function createProduct(Menu $menu, ?Category $category = null): Product
    {
        $product = Product::factory()->create([
            'menu_id' => $menu->id,
            'is_active' => true,
        ]);

        if ($category) {
            $product->categories()->attach($category);
        }

        return $product;
    }
}
