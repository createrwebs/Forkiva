<?php

namespace Modules\Menu\tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Branch\Models\Branch;
use Modules\Category\Models\Category;
use Modules\Menu\Models\Menu;
use Modules\Menu\Models\OnlineMenu;
use Modules\Product\Models\Product;
use Tests\TestCase;

class OnlineMenuTest extends TestCase
{
    use RefreshDatabase;

    public function test_public_online_menu_returns_all_products_as_an_array(): void
    {
        $branch = Branch::factory()->create();
        $menu = Menu::factory()->create([
            'branch_id' => $branch->id,
            'is_active' => true,
        ]);
        $category = Category::query()->create([
            'name' => ['en' => 'Meals'],
            'menu_id' => $menu->id,
            'is_active' => true,
        ]);
        $onlineMenu = OnlineMenu::query()->create([
            'branch_id' => $branch->id,
            'menu_id' => $menu->id,
            'name' => ['en' => 'Public menu'],
            'slug' => 'public-menu',
            'is_active' => true,
        ]);

        Product::factory()
            ->count(30)
            ->create([
                'menu_id' => $menu->id,
                'is_active' => true,
            ])
            ->each(fn (Product $product) => $product->categories()->attach($category));

        $response = $this->getJson("/api/v1/online-menus/{$onlineMenu->slug}/menu");

        $response
            ->assertOk()
            ->assertJsonCount(30, 'body.products')
            ->assertJsonStructure([
                'body' => [
                    'products' => [
                        '*' => [
                            'id',
                            'name',
                            'category_ids',
                        ],
                    ],
                ],
            ]);
    }
}
