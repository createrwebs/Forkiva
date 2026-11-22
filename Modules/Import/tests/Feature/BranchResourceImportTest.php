<?php

namespace Modules\Import\Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Branch\Models\Branch;
use Modules\Import\Enums\ImportMode;
use Modules\Import\Enums\ImportOperation;
use Modules\Import\Models\DataImport;
use Modules\Import\Support\ImportDefinitionResolver;
use Modules\Pos\Models\PosRegister;
use Modules\Printer\Models\Printer;
use Modules\SeatingPlan\Models\Floor;
use Modules\SeatingPlan\Models\Table;
use Modules\SeatingPlan\Models\Zone;
use Modules\User\Models\Permission;
use Modules\User\Models\User;
use Tests\TestCase;

class BranchResourceImportTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        app()->instance('setting', new class
        {
            public function get(string $key, mixed $default = null): mixed
            {
                return match ($key) {
                    'default_locale' => 'en',
                    'supported_locales' => ['en', 'ar'],
                    default => $default,
                };
            }
        });
    }

    public function test_register_floor_zone_and_table_rows_can_be_validated_and_applied(): void
    {
        $branch = Branch::factory()->create(['is_active' => true]);
        $this->actingAs($this->actor($branch->id), 'api');
        $import = new DataImport(['mode' => ImportMode::Upsert, 'context' => ['branch_id' => $branch->id]]);

        $floor = $this->apply('floors', ['name' => 'Main', 'name_ar' => 'الرئيسي', 'is_active' => 'yes'], $import);
        $this->assertSame('الرئيسي', $floor->getTranslation('name', 'ar'));

        $zone = $this->apply('zones', ['name' => 'Dining', 'floor_id' => $floor->id, 'is_active' => '1'], $import);
        $table = $this->apply('tables', ['name' => 'T1', 'floor_id' => $floor->id, 'zone_id' => $zone->id, 'capacity' => 4, 'shape' => 'square', 'is_active' => 'true'], $import);
        $register = $this->apply('pos_registers', ['name' => 'Front', 'code' => 'FRONT', 'is_active' => 'yes'], $import);

        $this->assertSame($branch->id, $table->branch_id);
        $this->assertSame($zone->id, $table->zone_id);
        $this->assertSame('FRONT', $register->code);

        $definition = app(ImportDefinitionResolver::class)->resolve('pos_registers');
        $result = $definition->validate(['id' => $register->id, 'name' => 'Front Updated', 'code' => 'FRONT', 'is_active' => 1], $import);
        $this->assertSame(ImportOperation::Update, $result['operation']);
        $this->assertSame([], $result['errors']);
        $definition->apply($result['data'], $import, $result['target_id']);
        $this->assertSame('Front Updated', $register->fresh()->getTranslation('name', 'en'));
    }

    public function test_table_import_rejects_a_zone_from_another_floor(): void
    {
        $branch = Branch::factory()->create(['is_active' => true]);
        $this->actingAs($this->actor($branch->id), 'api');
        $floor = Floor::factory()->create(['branch_id' => $branch->id, 'name' => ['en' => 'Main'], 'is_active' => true]);
        $otherFloor = Floor::factory()->create(['branch_id' => $branch->id, 'name' => ['en' => 'Other'], 'is_active' => true]);
        $zone = Zone::factory()->create(['branch_id' => $branch->id, 'floor_id' => $otherFloor->id, 'name' => ['en' => 'Other'], 'is_active' => true]);
        $import = new DataImport(['mode' => ImportMode::Upsert, 'context' => ['branch_id' => $branch->id]]);

        $result = app(ImportDefinitionResolver::class)->resolve('tables')->validate([
            'name' => 'T1', 'floor_id' => $floor->id, 'zone_id' => $zone->id,
            'capacity' => 2, 'shape' => 'square', 'is_active' => 1,
        ], $import);

        $this->assertSame(ImportOperation::Invalid, $result['operation']);
        $this->assertArrayHasKey('zone_id', $result['errors']);
        $this->assertSame(0, Table::query()->count());
    }

    public function test_zone_and_table_import_offer_active_floor_and_zone_ids_from_the_assigned_branch(): void
    {
        $branch = Branch::factory()->create(['is_active' => true]);
        $otherBranch = Branch::factory()->create(['is_active' => true]);
        $floor = Floor::factory()->create(['branch_id' => $branch->id, 'name' => ['en' => 'Main'], 'is_active' => true]);
        $inactiveFloor = Floor::factory()->create(['branch_id' => $branch->id, 'name' => ['en' => 'Closed'], 'is_active' => false]);
        $otherFloor = Floor::factory()->create(['branch_id' => $otherBranch->id, 'name' => ['en' => 'Other'], 'is_active' => true]);
        $zone = Zone::factory()->create(['branch_id' => $branch->id, 'floor_id' => $floor->id, 'name' => ['en' => 'Dining'], 'is_active' => true]);
        Zone::factory()->create(['branch_id' => $branch->id, 'floor_id' => $floor->id, 'name' => ['en' => 'Inactive'], 'is_active' => false]);
        Zone::factory()->create(['branch_id' => $branch->id, 'floor_id' => $inactiveFloor->id, 'name' => ['en' => 'Closed'], 'is_active' => true]);
        Zone::factory()->create(['branch_id' => $otherBranch->id, 'floor_id' => $otherFloor->id, 'name' => ['en' => 'Other'], 'is_active' => true]);
        $this->actingAs($this->actor($branch->id), 'api');
        $zoneFields = collect($this->getJson('/api/v1/imports/zones/fields')->assertOk()->json('body'))->keyBy('key');
        $tableFields = collect($this->getJson('/api/v1/imports/tables/fields')->assertOk()->json('body'))->keyBy('key');
        $floorOptions = [[
            'value' => (string) $floor->id,
            'label' => 'Main',
            'matches' => ['branch_id' => (string) $branch->id],
        ]];

        $this->assertSame($floorOptions, $zoneFields->get('floor_id')['options']);
        $this->assertSame($floorOptions, $tableFields->get('floor_id')['options']);
        $this->assertSame([
            [
                'value' => (string) $zone->id,
                'label' => 'Dining (Main)',
                'matches' => [
                    'branch_id' => (string) $branch->id,
                    'floor_id' => (string) $floor->id,
                ],
            ],
        ], $tableFields->get('zone_id')['options']);
    }

    public function test_register_import_offers_branch_printers_and_saves_waiter_and_delivery_printers(): void
    {
        $branch = Branch::factory()->create(['is_active' => true]);
        $otherBranch = Branch::factory()->create(['is_active' => true]);
        $waiterPrinter = Printer::query()->forceCreate([
            'branch_id' => $branch->id,
            'name' => ['en' => 'Waiter'],
            'is_active' => true,
        ]);
        $deliveryPrinter = Printer::query()->forceCreate([
            'branch_id' => $branch->id,
            'name' => ['en' => 'Delivery'],
            'is_active' => true,
        ]);
        $inactivePrinter = Printer::query()->forceCreate([
            'branch_id' => $branch->id,
            'name' => ['en' => 'Inactive'],
            'is_active' => false,
        ]);
        $otherPrinter = Printer::query()->forceCreate([
            'branch_id' => $otherBranch->id,
            'name' => ['en' => 'Other'],
            'is_active' => true,
        ]);
        $this->actingAs($this->actor($branch->id), 'api');
        $definition = app(ImportDefinitionResolver::class)->resolve('pos_registers');
        $fields = collect($definition->fields())->keyBy('key');

        foreach (['invoice', 'bill', 'waiter', 'delivery'] as $type) {
            $this->assertSame([
                [
                    'value' => (string) $waiterPrinter->id,
                    'label' => 'Waiter',
                    'matches' => ['branch_id' => (string) $branch->id],
                ],
                [
                    'value' => (string) $deliveryPrinter->id,
                    'label' => 'Delivery',
                    'matches' => ['branch_id' => (string) $branch->id],
                ],
            ], $fields->get("{$type}_printer_id")['options']);
        }

        $import = new DataImport(['mode' => ImportMode::Upsert, 'context' => ['branch_id' => $branch->id]]);
        $register = $this->apply('pos_registers', [
            'name' => 'Front',
            'code' => 'FRONT',
            'is_active' => 'yes',
            'waiter_printer_id' => (string) $waiterPrinter->id,
            'delivery_printer_id' => (string) $deliveryPrinter->id,
        ], $import);

        $this->assertSame($waiterPrinter->id, $register->waiter_printer_id);
        $this->assertSame($deliveryPrinter->id, $register->delivery_printer_id);

        foreach ([$inactivePrinter, $otherPrinter] as $printer) {
            $result = $definition->validate([
                'name' => 'Invalid',
                'code' => 'INVALID',
                'is_active' => 1,
                'waiter_printer_id' => $printer->id,
            ], $import);

            $this->assertSame(ImportOperation::Invalid, $result['operation']);
            $this->assertArrayHasKey('waiter_printer_id', $result['errors']);
        }
    }

    private function apply(string $resource, array $data, DataImport $import): Floor|Zone|Table|PosRegister
    {
        $definition = app(ImportDefinitionResolver::class)->resolve($resource);
        $result = $definition->validate($data, $import);
        $this->assertSame([], $result['errors']);
        $this->assertSame(ImportOperation::Create, $result['operation']);
        $definition->apply($result['data'], $import, $result['target_id']);

        return match ($resource) {
            'floors' => Floor::query()->firstOrFail(),
            'zones' => Zone::query()->firstOrFail(),
            'tables' => Table::query()->firstOrFail(),
            'pos_registers' => PosRegister::query()->firstOrFail(),
        };
    }

    private function actor(int $branchId): User
    {
        $permissions = [];
        foreach (['floors', 'zones', 'tables', 'pos_registers'] as $resource) {
            foreach (['import', 'create', 'edit'] as $action) {
                $permissions[] = "admin.$resource.$action";
            }
        }
        $user = User::factory()->create(['is_active' => true, 'branch_id' => $branchId]);
        foreach ($permissions as $permission) {
            Permission::query()->firstOrCreate(['name' => $permission, 'guard_name' => 'api']);
        }
        $user->givePermissionTo($permissions);

        return $user;
    }
}
