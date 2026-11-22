<?php

namespace Modules\Category\tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Category\Models\Category;
use Modules\Category\Services\CategoryTreeUpdater\CategoryTreeUpdaterServiceInterface;
use Modules\Menu\Models\Menu;
use Tests\TestCase;

class CategoryTreeUpdaterTest extends TestCase
{
    use RefreshDatabase;

    public function test_reordering_handles_null_parents_and_preserves_menu_boundaries(): void
    {
        $menu = Menu::factory()->create();
        $first = Category::unguarded(fn () => Category::create(['menu_id' => $menu->id, 'name' => ['en' => 'First'], 'slug' => 'first', 'order' => 0]));
        $second = Category::unguarded(fn () => Category::create(['menu_id' => $menu->id, 'name' => ['en' => 'Second'], 'slug' => 'second', 'order' => 1]));
        $other = Category::unguarded(fn () => Category::create(['menu_id' => Menu::factory()->create()->id, 'name' => ['en' => 'Other'], 'slug' => 'other', 'order' => 5]));
        $service = app(CategoryTreeUpdaterServiceInterface::class);
        $service->update($menu->id, [
            ['id' => $second->id, 'parent' => null],
            ['id' => $first->id, 'parent' => null],
            ['id' => $other->id, 'parent' => null],
        ]);
        $this->assertEquals(0, $second->fresh()->order);
        $this->assertEquals(1, $first->fresh()->order);
        $this->assertEquals(5, $other->fresh()->order);
        $this->assertNull($first->fresh()->parent_id);
        $service->update($menu->id, []);
    }

    public function test_parent_updates_repair_the_tree_without_changing_sibling_order(): void
    {
        $menu = Menu::factory()->create();
        $parent = Category::unguarded(fn () => Category::create([
            'menu_id' => $menu->id,
            'name' => ['en' => 'Parent'],
            'slug' => 'parent',
            'is_active' => false,
            'order' => 4,
        ]));
        $child = Category::unguarded(fn () => Category::create([
            'menu_id' => $menu->id,
            'name' => ['en' => 'Child'],
            'slug' => 'child',
            'order' => 7,
        ]));

        app(CategoryTreeUpdaterServiceInterface::class)->updateParents($menu->id, [$child->id => $parent->id]);

        $this->assertSame($parent->id, $child->fresh()->parent_id);
        $this->assertSame(7, $child->fresh()->order);
        $this->assertTrue($child->fresh()->isDescendantOf($parent->fresh()));
    }
}
