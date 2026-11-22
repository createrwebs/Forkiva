<?php

namespace Modules\Inventory\Tests\Feature;

use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Modules\Branch\Models\Branch;
use Modules\Inventory\Enums\PurchaseStatus;
use Modules\Inventory\Enums\StockMovementType;
use Modules\Inventory\Models\Ingredient;
use Modules\Inventory\Models\Purchase;
use Modules\Inventory\Models\StockMovement;
use Modules\Inventory\Models\Supplier;
use Modules\Inventory\Models\Unit;
use Modules\User\Models\Permission;
use Modules\User\Models\User;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class InventoryAnalyticsOverviewTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;

    protected function setUp(): void
    {
        parent::setUp();

        app()->instance('setting', new class
        {
            public function get(string $key, mixed $default = null): mixed
            {
                return match ($key) {
                    'default_currency' => 'JOD',
                    'default_locale' => 'en',
                    default => $default,
                };
            }
        });

        Cache::flush();
        CarbonImmutable::setTestNow('2026-09-07 12:00:00');
        $this->user = User::factory()->create(['is_active' => true]);
        app(PermissionRegistrar::class)->forgetCachedPermissions();
        Permission::query()->firstOrCreate([
            'name' => 'admin.inventories.analytics',
            'guard_name' => 'api',
        ]);
        $this->user->givePermissionTo('admin.inventories.analytics');
        $this->actingAs($this->user);
    }

    protected function tearDown(): void
    {
        CarbonImmutable::setTestNow();
        parent::tearDown();
    }

    public function test_analytics_returns_inventory_health_widgets_for_the_selected_period(): void
    {
        $branch = Branch::factory()->create(['currency' => 'JOD']);
        $unit = Unit::query()->create([
            'name' => ['en' => 'Kilogram', 'ar' => 'كيلوغرام'],
            'symbol' => ['en' => 'kg', 'ar' => 'كغ'],
            'type' => 'mass',
        ]);
        $healthy = $this->ingredient($branch, $unit, 'Flour', 10, 5, 5);
        $this->ingredient($branch, $unit, 'Oil', 2, 4, 3);
        $supplier = Supplier::factory()->create(['branch_id' => $branch->id]);

        Purchase::factory()->create([
            'branch_id' => $branch->id,
            'supplier_id' => $supplier->id,
            'currency' => 'JOD',
            'currency_rate' => 1,
            'status' => PurchaseStatus::Received,
            'sub_total' => 120,
            'total' => 120,
            'created_at' => '2026-09-03 10:00:00',
        ]);
        StockMovement::factory()->create([
            'branch_id' => $branch->id,
            'ingredient_id' => $healthy->id,
            'unit_id' => $unit->id,
            'type' => StockMovementType::Out,
            'quantity' => 8,
            'unit_cost' => 10,
            'currency' => 'JOD',
            'currency_rate' => 1,
            'created_at' => '2026-09-04 10:00:00',
        ]);
        StockMovement::factory()->create([
            'branch_id' => $branch->id,
            'ingredient_id' => $healthy->id,
            'unit_id' => $unit->id,
            'type' => StockMovementType::Waste,
            'quantity' => 2,
            'unit_cost' => 10,
            'currency' => 'JOD',
            'currency_rate' => 1,
            'created_at' => '2026-09-05 10:00:00',
        ]);

        $this->getJson('/api/v1/inventories/analytics?'.http_build_query([
            'branch_id' => $branch->id,
            'from' => '2026-09-01',
            'to' => '2026-09-07',
            'widgets' => ['inventory_value', 'low_stock_items', 'purchase_spend', 'waste_rate'],
        ]))
            ->assertOk()
            ->assertJsonPath('body.meta.branch_id', $branch->id)
            ->assertJsonPath('body.meta.branch_locked', false)
            ->assertJsonPath('body.meta.display_currency', 'JOD')
            ->assertJsonPath('body.widgets.inventory_value.data.value.amount', 58)
            ->assertJsonPath('body.widgets.low_stock_items.data.value', 1)
            ->assertJsonPath('body.widgets.purchase_spend.data.value.amount', 120)
            ->assertJsonPath('body.widgets.waste_rate.data.value', 20);
    }

    public function test_filters_follow_the_dashboard_api_contract(): void
    {
        $this->getJson('/api/v1/inventories/analytics/filters')
            ->assertOk()
            ->assertJsonPath('body.defaults.branch_id', null)
            ->assertJsonPath('body.defaults.from', '2026-08-09')
            ->assertJsonPath('body.defaults.to', '2026-09-07')
            ->assertJsonPath('body.meta.branch_locked', false)
            ->assertJsonPath('body.meta.timezone', config('app.timezone'));
    }

    public function test_analytics_returns_only_requested_widgets_in_dashboard_structure(): void
    {
        $this->getJson('/api/v1/inventories/analytics?'.http_build_query([
            'widgets' => ['inventory_value', 'stock_movement'],
        ]))
            ->assertOk()
            ->assertJsonPath('body.meta.branch_id', null)
            ->assertJsonPath('body.widgets.inventory_value.key', 'inventory_value')
            ->assertJsonPath('body.widgets.inventory_value.permission', 'admin.inventories.analytics')
            ->assertJsonPath('body.widgets.inventory_value.authorized', true)
            ->assertJsonPath('body.widgets.inventory_value.data.value.amount', 0)
            ->assertJsonPath('body.widgets.stock_movement.key', 'stock_movement')
            ->assertJsonMissingPath('body.widgets.purchase_spend');

        $this->getJson('/api/v1/inventories/analytics?'.http_build_query([
            'widgets' => ['unknown_widget'],
        ]))->assertUnprocessable();
    }

    public function test_assigned_user_cannot_read_another_branch_inventory_analytics(): void
    {
        $assignedBranch = Branch::factory()->create(['currency' => 'JOD']);
        $otherBranch = Branch::factory()->create(['currency' => 'JOD']);
        $unit = Unit::query()->create([
            'name' => ['en' => 'Kilogram', 'ar' => 'كيلوغرام'],
            'symbol' => ['en' => 'kg', 'ar' => 'كغ'],
            'type' => 'mass',
        ]);
        $assignedIngredient = $this->ingredient($assignedBranch, $unit, 'Flour', 10, 5, 5);
        $otherIngredient = $this->ingredient($otherBranch, $unit, 'Oil', 10, 5, 5);
        $this->stockMovement($assignedBranch, $assignedIngredient, $unit, 2);
        $this->stockMovement($otherBranch, $otherIngredient, $unit, 7);
        $this->user->forceFill(['branch_id' => $assignedBranch->id])->save();
        $this->actingAs($this->user->fresh());

        $this->getJson('/api/v1/inventories/analytics?'.http_build_query([
            'branch_id' => $otherBranch->id,
            'from' => '2026-09-01',
            'to' => '2026-09-07',
            'widgets' => ['wastage_spoilage'],
        ]))->assertNotFound();

        $this->getJson('/api/v1/inventories/analytics?'.http_build_query([
            'from' => '2026-09-01',
            'to' => '2026-09-07',
            'widgets' => ['wastage_spoilage'],
        ]))
            ->assertOk()
            ->assertJsonPath('body.meta.branch_id', $assignedBranch->id)
            ->assertJsonPath('body.meta.branch_locked', true)
            ->assertJsonPath('body.widgets.wastage_spoilage.data.datasets.0.data.0', 2);
    }

    private function ingredient(
        Branch $branch,
        Unit $unit,
        string $name,
        float $stock,
        float $cost,
        float $alert,
    ): Ingredient {
        return Ingredient::query()->create([
            'name' => ['en' => $name, 'ar' => $name],
            'branch_id' => $branch->id,
            'unit_id' => $unit->id,
            'cost_per_unit' => $cost,
            'alert_quantity' => $alert,
            'current_stock' => $stock,
            'is_returnable' => false,
        ]);
    }

    private function stockMovement(
        Branch $branch,
        Ingredient $ingredient,
        Unit $unit,
        float $quantity,
    ): StockMovement {
        return StockMovement::factory()->create([
            'branch_id' => $branch->id,
            'ingredient_id' => $ingredient->id,
            'unit_id' => $unit->id,
            'type' => StockMovementType::Waste,
            'quantity' => $quantity,
            'unit_cost' => 5,
            'currency' => 'JOD',
            'currency_rate' => 1,
            'created_at' => '2026-09-05 10:00:00',
        ]);
    }
}
