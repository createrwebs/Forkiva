<?php

namespace Modules\Inventory\Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Modules\Branch\Models\Branch;
use Modules\Import\Enums\ImportMode;
use Modules\Import\Enums\ImportOperation;
use Modules\Import\Jobs\ApplyImportJob;
use Modules\Import\Jobs\FinalizeImportJob;
use Modules\Import\Jobs\StageImportJob;
use Modules\Import\Jobs\ValidateImportJob;
use Modules\Import\Models\DataImport;
use Modules\Import\Support\ErrorWorkbookWriter;
use Modules\Import\Support\ImportDefinitionResolver;
use Modules\Inventory\Models\Ingredient;
use Modules\Inventory\Models\Supplier;
use Modules\Inventory\Models\Unit;
use Modules\User\Models\Permission;
use Modules\User\Models\User;
use Tests\TestCase;

class InventoryImportTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('import.disk', 'local');
        Storage::fake('local');
        Queue::fake();

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

    public function test_unit_csv_runs_through_mapping_validation_and_apply(): void
    {
        $actor = $this->actor(['admin.units.import']);
        $this->actingAs($actor, 'api');
        $id = $this->postJson('/api/v1/imports/units', [
            'file' => UploadedFile::fake()->createWithContent('units.csv', "Name,Symbol,Type\nGram,g,mass\n"),
        ])->assertCreated()->assertJsonPath('body.mapping.name', 0)->json('body.id');
        $this->postJson("/api/v1/imports/$id/mapping", [
            'mapping' => ['name' => 0, 'symbol' => 1, 'type' => 2],
        ])->assertOk();
        $fake = Queue::getFacadeRoot();
        Queue::swap($fake->queue);
        try {
            (new StageImportJob($id, 1))->handle(app(ImportDefinitionResolver::class));
        } finally {
            Queue::swap($fake);
        }
        $this->actingAs($actor, 'api');
        $this->postJson("/api/v1/imports/$id/validate", ['mode' => 'create_only'])->assertOk();
        (new ValidateImportJob($id, DataImport::findOrFail($id)->revision))
            ->handle(app(ImportDefinitionResolver::class), app(ErrorWorkbookWriter::class));
        $this->actingAs($actor, 'api');
        $this->getJson("/api/v1/imports/$id")->assertJsonPath('body.new_rows', 1);
        $this->postJson("/api/v1/imports/$id/queue")->assertOk();
        (new ApplyImportJob($id, DataImport::findOrFail($id)->revision))->handle(app(ImportDefinitionResolver::class));
        (new FinalizeImportJob($id, DataImport::findOrFail($id)->revision))->handle(app(ImportDefinitionResolver::class));
        $this->assertSame('Gram', Unit::query()->firstOrFail()->getTranslation('name', 'en'));
    }

    public function test_units_import_creates_and_updates_translations(): void
    {
        $this->actingAs($this->actor(['admin.units.import']), 'api');
        $definition = app(ImportDefinitionResolver::class)->resolve('units');
        $this->getJson('/api/v1/imports/units/fields')->assertOk();
        $import = new DataImport(['mode' => ImportMode::Upsert, 'context' => []]);
        $data = ['name' => 'Kilogram', 'symbol' => 'kg', 'type' => 'mass', 'name_ar' => 'كيلوغرام'];
        $result = $definition->validate($data, $import);
        $this->assertSame(ImportOperation::Create, $result['operation']);
        $this->assertSame([], $result['errors']);
        $definition->apply($result['data'], $import, $result['target_id']);
        $unit = Unit::query()->firstOrFail();
        $this->assertSame('كيلوغرام', $unit->getTranslation('name', 'ar'));

        $result = $definition->validate($data, $import);
        $this->assertSame(ImportOperation::Update, $result['operation']);
        $definition->apply($result['data'], $import, $result['target_id']);
        $this->assertSame(1, Unit::query()->count());
    }

    public function test_suppliers_import_stays_in_assigned_branch_and_preserves_blank_optional_values(): void
    {
        $branch = Branch::factory()->create();
        $other = Branch::factory()->create();
        $this->actingAs($this->actor(['admin.suppliers.import'], $branch->id), 'api');
        $definition = app(ImportDefinitionResolver::class)->resolve('suppliers');
        $this->getJson('/api/v1/imports/suppliers/fields')->assertOk();
        $import = new DataImport(['mode' => ImportMode::Upsert, 'context' => ['branch_id' => $branch->id]]);
        $outside = Supplier::withoutEvents(fn () => Supplier::factory()->create(['branch_id' => $other->id]));
        $this->assertArrayHasKey('id', $definition->validate(['id' => $outside->id, 'name' => 'Outside'], $import)['errors']);
        $data = ['name' => 'Grower', 'phone' => '123', 'branch_id' => $other->id];
        $result = $definition->validate($data, $import);
        $this->assertSame([], $result['errors']);
        $definition->apply($result['data'], $import, $result['target_id']);
        $supplier = Supplier::query()->firstOrFail();
        $this->assertSame($branch->id, $supplier->branch_id);

        $result = $definition->validate(['name' => 'Grower', 'phone' => ''], $import);
        $definition->apply($result['data'], $import, $result['target_id']);
        $this->assertSame('123', $supplier->fresh()->phone);
        $this->assertSame(1, Supplier::query()->where('branch_id', $branch->id)->count());
    }

    public function test_ingredients_import_preserves_stock_and_rejects_invalid_unit(): void
    {
        $branch = Branch::factory()->create();
        $this->actingAs($this->actor(['admin.ingredients.import'], $branch->id), 'api');
        $unit = Unit::query()->create(['name' => ['en' => 'Kilogram'], 'symbol' => ['en' => 'kg'], 'type' => 'mass']);
        $definition = app(ImportDefinitionResolver::class)->resolve('ingredients');
        $this->getJson('/api/v1/imports/ingredients/fields')->assertOk();
        $import = new DataImport(['mode' => ImportMode::Upsert, 'context' => ['branch_id' => $branch->id]]);
        $data = ['name' => 'Flour', 'unit_id' => $unit->id, 'cost_per_unit' => '0', 'alert_quantity' => '0', 'is_returnable' => '0'];
        $this->assertArrayHasKey('unit_id', $definition->validate([...$data, 'unit_id' => 999999], $import)['errors']);
        $result = $definition->validate($data, $import);
        $this->assertSame([], $result['errors']);
        $definition->apply($result['data'], $import, $result['target_id']);
        $ingredient = Ingredient::query()->firstOrFail();
        $this->assertSame($branch->id, $ingredient->branch_id);
        $this->assertEquals(0, $ingredient->current_stock);
        $this->assertFalse($ingredient->is_returnable);
        $ingredient->update(['current_stock' => 7]);

        $result = $definition->validate([...$data, 'cost_per_unit' => '3.0000'], $import);
        $this->assertSame(ImportOperation::Update, $result['operation']);
        $definition->apply($result['data'], $import, $result['target_id']);
        $this->assertEquals(7, $ingredient->fresh()->current_stock);
    }

    private function actor(array $permissions, ?int $branchId = null): User
    {
        $user = User::factory()->create(['is_active' => true, 'branch_id' => $branchId]);
        foreach ($permissions as $permission) {
            Permission::query()->firstOrCreate(['name' => $permission, 'guard_name' => 'api']);
        }
        $user->givePermissionTo($permissions);

        return $user;
    }
}
