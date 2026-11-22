<?php

namespace Modules\Loyalty\Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Loyalty\Database\Seeders\LoyaltyDatabaseSeeder;
use Modules\Loyalty\Enums\LoyaltyRewardType;
use Modules\Loyalty\Models\LoyaltyReward;
use Modules\Menu\Models\Menu;
use Modules\Product\Models\Product;
use Tests\TestCase;

class LoyaltyInstallationSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_demo_installation_seeds_free_item_rewards_with_an_available_product(): void
    {
        config(['app.seed_demo_data' => true]);
        fake()->seed(1234);
        $product = Product::factory()->create(['sku' => 'LOYALTY-DEMO-ITEM', 'menu_id' => Menu::factory()->create()->id]);

        $this->seed(LoyaltyDatabaseSeeder::class);

        $this->assertSame(20, LoyaltyReward::query()->count());
        $rewards = LoyaltyReward::query()->where('type', LoyaltyRewardType::FreeItem)->get();
        $this->assertNotEmpty($rewards);
        foreach ($rewards as $reward) {
            $this->assertSame($product->sku, $reward->meta['product_sku']);
        }
    }

    public function test_demo_installation_can_seed_rewards_without_available_products(): void
    {
        config(['app.seed_demo_data' => true]);
        fake()->seed(1234);

        $this->seed(LoyaltyDatabaseSeeder::class);

        $this->assertSame(20, LoyaltyReward::query()->count());
        $this->assertFalse(LoyaltyReward::query()->where('type', LoyaltyRewardType::FreeItem)->exists());
    }
}
