<?php

namespace Modules\Import\Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Maatwebsite\Excel\Jobs\QueueImport;
use Modules\Branch\Models\Branch;
use Modules\Category\Models\Category;
use Modules\Import\Enums\ImportMode;
use Modules\Import\Enums\ImportOperation;
use Modules\Import\Enums\ImportRowStatus;
use Modules\Import\Enums\ImportStatus;
use Modules\Import\Jobs\ApplyImportJob;
use Modules\Import\Jobs\FinalizeImportJob;
use Modules\Import\Jobs\StageImportJob;
use Modules\Import\Jobs\ValidateImportJob;
use Modules\Import\Models\DataImport;
use Modules\Import\Models\ImportRow;
use Modules\Import\Services\Import\ImportServiceInterface;
use Modules\Import\Support\ErrorWorkbookWriter;
use Modules\Import\Support\ImportDefinitionResolver;
use Modules\Import\Support\ImportTemplate;
use Modules\Import\Transformers\Api\V1\ImportResource;
use Modules\Import\Transformers\Api\V1\ImportRowResource;
use Modules\Inventory\Models\Ingredient;
use Modules\Inventory\Models\Unit;
use Modules\Menu\Imports\MenuImportDefinition;
use Modules\Menu\Models\Menu;
use Modules\Product\Imports\ProductImportDefinition;
use Modules\Product\Models\Product;
use Modules\Tax\Models\Tax;
use Modules\User\Models\Permission;
use Modules\User\Models\User;
use PhpOffice\PhpSpreadsheet\IOFactory;
use Tests\TestCase;

class ImportWorkflowTest extends TestCase
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

    public function test_all_import_enums_have_translations_in_supported_languages(): void
    {
        foreach ([ImportMode::class, ImportStatus::class, ImportOperation::class, ImportRowStatus::class] as $enum) {
            $this->assertSame(array_column($enum::cases(), 'value'), $enum::values());
            foreach (['en', 'ar'] as $locale) {
                app()->setLocale($locale);
                foreach ($enum::cases() as $case) {
                    $this->assertNotSame($case->transKey(), $case->trans());
                    $this->assertSame(['id' => $case->value, 'name' => $case->trans()], $case->toTrans());
                }
            }
        }
    }

    public function test_import_translations_are_published_under_module_keys(): void
    {
        $translations = \Modules\Translation\Models\Translation::appRetrieve();
        foreach (['en', 'ar'] as $locale) {
            $messages = $translations[$locale];
            foreach ([
                'import::imports.title',
                'import::imports.import_units',
                'import::imports.import_ingredients',
                'import::imports.import_suppliers',
                'import::imports.import_customers',
                'import::imports.import_users',
                'import::imports.mapping_summary',
                'import::imports.upload_new',
                'import::imports.modes.create_only',
                'import::imports.statuses.ready',
                'import::imports.operations.create',
                'import::imports.row_statuses.pending',
                'import::attributes.imports.file',
            ] as $key) {
                $this->assertArrayHasKey($key, $messages);
                $this->assertSame(__($key, [], $locale), $messages[$key]);
                $this->assertNotSame($key, $messages[$key]);
            }
            $this->assertSame([], array_filter(array_keys($messages), fn ($key) => str_starts_with($key, 'import::imports.ui.')));
        }
        $englishKeys = array_keys(array_filter($translations['en'], fn ($key) => str_starts_with($key, 'import::'), ARRAY_FILTER_USE_KEY));
        $arabicKeys = array_keys(array_filter($translations['ar'], fn ($key) => str_starts_with($key, 'import::'), ARRAY_FILTER_USE_KEY));
        $this->assertEqualsCanonicalizing($englishKeys, $arabicKeys);
    }

    public function test_import_resources_use_translated_enums_and_system_dates(): void
    {
        app()->setLocale('ar');
        app()->instance('setting', new class
        {
            public function get(string $key, mixed $default = null): mixed
            {
                return match ($key) {
                    'default_date_format' => 'd/m/Y',
                    'default_time_format' => 'H:i',
                    default => $default,
                };
            }
        });
        $import = new DataImport([
            'status' => ImportStatus::Ready,
            'mode' => ImportMode::Upsert,
            'created_at' => '2026-09-28 13:45:00',
            'started_at' => null,
            'finished_at' => '2026-09-28 14:15:00',
        ]);
        $data = (new ImportResource($import))->resolve();
        $this->assertSame(ImportStatus::Ready->toTrans(), $data['status']);
        $this->assertSame(ImportMode::Upsert->toTrans(), $data['mode']);
        $this->assertSame('28/09/2026 13:45', $data['created_at']);
        $this->assertNull($data['started_at']);
        $this->assertSame('28/09/2026 14:15', $data['finished_at']);

        $row = new ImportRow(['status' => ImportRowStatus::Pending, 'operation' => null]);
        $data = (new ImportRowResource($row))->resolve();
        $this->assertSame(ImportRowStatus::Pending->toTrans(), $data['status']);
        $this->assertNull($data['operation']);
        $row->operation = ImportOperation::Create;
        $this->assertSame(ImportOperation::Create->toTrans(), (new ImportRowResource($row))->resolve()['operation']);
    }

    public function test_menu_import_maps_validates_and_applies_without_changing_other_branches(): void
    {
        $branch = Branch::factory()->create();
        $otherBranch = Branch::factory()->create();
        $actor = $this->actor(['admin.menus.import', 'admin.menus.create', 'admin.menus.edit'], $branch->id);
        $this->actingAs($actor, 'api');
        $existing = Menu::factory()->create(['branch_id' => $branch->id, 'name' => ['en' => 'Lunch'], 'is_active' => false]);
        $unrelated = Menu::factory()->create(['branch_id' => $otherBranch->id, 'name' => ['en' => 'Lunch'], 'is_active' => false]);

        $response = $this->postJson('/api/v1/imports/menus', [
            'file' => UploadedFile::fake()->createWithContent('menus.csv', implode("\n", [
                'Name,Description,Name Ar,Description Ar,Active',
                'Lunch,Updated,غداء,تحديث,0',
                'Dinner,New,عشاء,جديد,1',
                '',
            ])),
        ])->assertCreated();
        $id = $response->json('body.id');
        $this->assertSame($actor->id, DataImport::findOrFail($id)->created_by);
        $response->assertJsonPath('body.mapping.name', 0);
        $response->assertJsonPath('body.mode.id', 'create_only');
        $this->postJson("/api/v1/imports/$id/mapping", [
            'mapping' => ['name' => 0, 'description' => 1, 'name_ar' => 2, 'description_ar' => 3, 'is_active' => 4],
        ])->assertOk();
        $this->stage($id);
        $this->assertSame(2, DataImport::findOrFail($id)->total_rows);
        $this->actingAs($actor, 'api');
        $this->getJson('/api/v1/imports?resource=menus')->assertJsonCount(1, 'body.data');

        $this->actingAs($actor, 'api');
        $this->postJson("/api/v1/imports/$id/validate", ['mode' => 'upsert'])->assertOk();
        (new ValidateImportJob($id, DataImport::findOrFail($id)->revision))->handle(app(ImportDefinitionResolver::class), app(ErrorWorkbookWriter::class));
        $this->actingAs($actor, 'api');
        $this->getJson("/api/v1/imports/$id")
            ->assertJsonPath('body.status.id', 'ready')
            ->assertJsonPath('body.new_rows', 1)
            ->assertJsonPath('body.update_rows', 1);
        $this->getJson("/api/v1/imports/$id/rows")
            ->assertJsonPath('body.data.0.status.id', 'valid')
            ->assertJsonPath('body.data.0.operation.id', 'update');

        $this->postJson("/api/v1/imports/$id/queue")->assertOk();
        (new ApplyImportJob($id, DataImport::findOrFail($id)->revision))->handle(app(ImportDefinitionResolver::class));
        (new FinalizeImportJob($id, DataImport::findOrFail($id)->revision))->handle(app(ImportDefinitionResolver::class));
        $this->assertSame(ImportStatus::Completed, DataImport::findOrFail($id)->status);
        $this->assertSame('Updated', $existing->fresh()->getTranslation('description', 'en'));
        $this->assertSame('غداء', $existing->fresh()->getTranslation('name', 'ar'));
        $this->assertNotSame('Updated', $unrelated->fresh()->getTranslation('description', 'en'));
        $this->assertTrue(Menu::query()
            ->withoutGlobalActive()
            ->where('branch_id', $branch->id)
            ->where('name->en', 'Dinner')
            ->exists());
    }

    public function test_product_import_updates_catalog_fields_and_preserves_product_relations(): void
    {
        $branch = Branch::factory()->create();
        $otherBranch = Branch::factory()->create();
        $menu = Menu::factory()->create(['branch_id' => $branch->id]);
        $otherMenu = Menu::factory()->create(['branch_id' => $otherBranch->id]);
        $actor = $this->actor(['admin.products.import', 'admin.products.create', 'admin.products.edit'], $branch->id);
        $this->actingAs($actor, 'api');
        $existing = Product::factory()->create(['menu_id' => $menu->id, 'sku' => 'TEA', 'name' => ['en' => 'Tea'], 'price' => 2, 'special_price' => null]);
        $other = Product::factory()->create(['menu_id' => $otherMenu->id, 'sku' => 'TEA', 'name' => ['en' => 'Tea'], 'price' => 7, 'special_price' => null]);
        $category = Category::query()->create([
            'menu_id' => $menu->id,
            'name' => ['en' => 'Drinks'],
            'slug' => 'drinks',
            'is_active' => true,
        ]);
        $desserts = Category::query()->create([
            'menu_id' => $menu->id,
            'name' => ['en' => 'Desserts'],
            'slug' => 'desserts',
            'is_active' => true,
        ]);
        $existing->categories()->attach($category);
        $tax = Tax::query()->create([
            'branch_id' => $branch->id,
            'name' => ['en' => 'Sales tax'],
            'code' => 'TAX1',
            'rate' => 5,
            'type' => 'exclusive',
            'is_global' => false,
            'is_active' => true,
        ]);
        $serviceTax = Tax::query()->create([
            'branch_id' => $branch->id,
            'name' => ['en' => 'Service tax'],
            'code' => 'TAX2',
            'rate' => 2,
            'type' => 'exclusive',
            'is_global' => false,
            'is_active' => true,
        ]);

        $csv = implode("\n", [
            'sku,name,price,special_price,special_price_type,special_price_start,special_price_end,new_from,new_to,prep_time_minutes,category_slugs,tax_ids,is_active',
            "TEA,Tea,3.25,1.00,fixed,2026-10-01,2026-10-31,2026-09-29,2026-10-15,4,drinks|desserts,{$tax->id}|{$serviceTax->id},1",
            "COFFEE,Coffee,4.50,,,,,,,3,\"drinks,desserts\",\"{$tax->id},{$serviceTax->id}\",1",
            '',
        ]);

        $this->postJson('/api/v1/imports/products', [
            'file' => UploadedFile::fake()->createWithContent('products.csv', $csv),
            'context' => ['menu_id' => $otherMenu->id],
        ])->assertNotFound();

        $id = $this->postJson('/api/v1/imports/products', [
            'file' => UploadedFile::fake()->createWithContent('products.csv', $csv),
            'context' => ['menu_id' => $menu->id],
        ])->assertCreated()->assertJsonPath('body.context.menu_id', $menu->id)->json('body.id');
        $this->postJson("/api/v1/imports/$id/mapping", [
            'mapping' => [
                'sku' => 0,
                'name' => 1,
                'price' => 2,
                'special_price' => 3,
                'special_price_type' => 4,
                'special_price_start' => 5,
                'special_price_end' => 6,
                'new_from' => 7,
                'new_to' => 8,
                'prep_time_minutes' => 9,
                'category_slugs' => 10,
                'tax_ids' => 11,
                'is_active' => 12,
            ],
        ])->assertOk();
        $this->stage($id);
        $this->actingAs($actor, 'api');
        $this->postJson("/api/v1/imports/$id/validate", ['mode' => 'upsert'])->assertOk();
        (new ValidateImportJob($id, DataImport::findOrFail($id)->revision))
            ->handle(app(ImportDefinitionResolver::class), app(ErrorWorkbookWriter::class));
        $this->actingAs($actor, 'api');
        $this->getJson("/api/v1/imports/$id")
            ->assertJsonPath('body.status.id', 'ready')
            ->assertJsonPath('body.new_rows', 1)
            ->assertJsonPath('body.update_rows', 1);
        $this->postJson("/api/v1/imports/$id/queue")->assertOk();
        (new ApplyImportJob($id, DataImport::findOrFail($id)->revision))->handle(app(ImportDefinitionResolver::class));
        (new FinalizeImportJob($id, DataImport::findOrFail($id)->revision))->handle(app(ImportDefinitionResolver::class));

        $this->assertSame(ImportStatus::Completed, DataImport::findOrFail($id)->status);
        $this->assertEquals(3.25, $existing->fresh()->getRawOriginal('price'));
        $this->assertEquals(7, $other->fresh()->getRawOriginal('price'));
        $this->assertEquals(1, $existing->fresh()->getRawOriginal('special_price'));
        $this->assertSame('fixed', $existing->fresh()->getRawOriginal('special_price_type'));
        $this->assertSame(4, $existing->fresh()->prep_time_minutes);
        $this->assertSame('2026-10-01', $existing->fresh()->special_price_start->toDateString());
        $this->assertSame('2026-10-15', $existing->fresh()->new_to->toDateString());
        $this->assertTrue($existing->fresh()->categories()->whereKey($category->id)->exists());
        $this->assertTrue($existing->fresh()->categories()->whereKey($desserts->id)->exists());
        $this->assertTrue($existing->fresh()->taxes()->whereKey($tax->id)->exists());
        $this->assertTrue($existing->fresh()->taxes()->whereKey($serviceTax->id)->exists());
        $coffee = Product::query()->withoutGlobalActive()->where('menu_id', $menu->id)->where('sku', 'COFFEE')->firstOrFail();
        $this->assertEqualsCanonicalizing([$category->id, $desserts->id], $coffee->categories()->pluck('categories.id')->all());
        $this->assertEqualsCanonicalizing([$tax->id, $serviceTax->id], $coffee->taxes()->pluck('taxes.id')->all());
    }

    public function test_product_import_clears_categories_and_taxes_after_explicit_row_edits(): void
    {
        $branch = Branch::factory()->create();
        $menu = Menu::factory()->create(['branch_id' => $branch->id]);
        $actor = $this->actor(['admin.products.import', 'admin.products.edit'], $branch->id);
        $this->actingAs($actor, 'api');
        $product = Product::factory()->create([
            'menu_id' => $menu->id,
            'sku' => 'TEA',
            'name' => ['en' => 'Tea'],
            'price' => 10,
            'special_price' => null,
            'special_price_type' => null,
        ]);
        $category = Category::query()->create([
            'menu_id' => $menu->id, 'name' => ['en' => 'Drinks'], 'slug' => 'drinks', 'is_active' => true,
        ]);
        $tax = Tax::query()->create([
            'branch_id' => $branch->id, 'name' => ['en' => 'Sales tax'], 'code' => 'TAX1',
            'rate' => 5, 'type' => 'exclusive', 'is_global' => false, 'is_active' => true,
        ]);
        $product->categories()->attach($category);
        $product->taxes()->attach($tax);

        $csv = "id,name,price,category_slugs,tax_ids,is_active\n{$product->id},Tea,10,,,1\n";
        $id = $this->postJson('/api/v1/imports/products', [
            'file' => UploadedFile::fake()->createWithContent('products.csv', $csv),
            'context' => ['menu_id' => $menu->id],
        ])->assertCreated()->json('body.id');
        $this->postJson("/api/v1/imports/$id/mapping", [
            'mapping' => ['id' => 0, 'name' => 1, 'price' => 2, 'category_slugs' => 3, 'tax_ids' => 4, 'is_active' => 5],
        ])->assertOk();
        $this->stage($id);
        $this->actingAs($actor, 'api');
        $row = DataImport::findOrFail($id)->rows()->firstOrFail();
        $this->patchJson("/api/v1/imports/$id/rows/$row->id", [
            'data' => ['category_slugs' => '[]', 'tax_ids' => '[]'],
        ])->assertOk();
        $this->postJson("/api/v1/imports/$id/validate", ['mode' => 'upsert'])->assertOk();
        (new ValidateImportJob($id, DataImport::findOrFail($id)->revision))
            ->handle(app(ImportDefinitionResolver::class), app(ErrorWorkbookWriter::class));
        $this->actingAs($actor, 'api');
        $this->getJson("/api/v1/imports/$id")->assertJsonPath('body.status.id', 'ready');
        $this->postJson("/api/v1/imports/$id/queue")->assertOk();
        (new ApplyImportJob($id, DataImport::findOrFail($id)->revision))->handle(app(ImportDefinitionResolver::class));
        (new FinalizeImportJob($id, DataImport::findOrFail($id)->revision))->handle(app(ImportDefinitionResolver::class));

        $this->assertFalse($product->fresh()->categories()->exists());
        $this->assertFalse($product->fresh()->taxes()->exists());
    }

    public function test_product_import_rejects_invalid_price_and_conflicting_sku(): void
    {
        $menu = Menu::factory()->create();
        $this->actingAs($this->actor(['admin.products.import', 'admin.products.edit']), 'api');
        $first = Product::factory()->create([
            'menu_id' => $menu->id,
            'sku' => 'FIRST',
            'special_price' => null,
            'special_price_type' => null,
        ]);
        Product::factory()->create(['menu_id' => $menu->id, 'sku' => 'SECOND']);
        $definition = app(ProductImportDefinition::class);
        $import = new DataImport(['context' => ['menu_id' => $menu->id], 'mode' => ImportMode::Upsert]);
        $row = ['id' => $first->id, 'sku' => 'SECOND', 'name' => 'Tea', 'price' => '2.50', 'is_active' => '1'];

        $result = $definition->validate($row, $import);
        $this->assertSame(['sku' => [__('product::products.import.sku_exists')]], $result['errors']);
        $this->assertSame(ImportOperation::Update, $result['operation']);

        $result = $definition->validate(array_replace($row, ['sku' => 'FIRST', 'price' => '-2']), $import);
        $this->assertArrayHasKey('price', $result['errors']);
        $this->assertSame(ImportOperation::Invalid, $result['operation']);
    }

    public function test_product_import_template_exposes_extended_catalog_fields(): void
    {
        $branch = Branch::factory()->create();
        $menu = Menu::factory()->create(['branch_id' => $branch->id]);
        $otherMenu = Menu::factory()->create(['branch_id' => Branch::factory()->create()->id]);
        Category::query()->create([
            'menu_id' => $menu->id, 'name' => ['en' => 'Drinks'], 'slug' => 'drinks', 'is_active' => true,
        ]);
        $tax = Tax::query()->create([
            'branch_id' => $branch->id, 'name' => ['en' => 'Sales tax'], 'code' => 'TAX1',
            'rate' => 5, 'type' => 'exclusive', 'is_global' => false, 'is_active' => true,
        ]);
        $this->actingAs($this->actor(['admin.products.import'], $branch->id), 'api');

        $this->getJson("/api/v1/imports/products/fields?menu_id={$otherMenu->id}")->assertNotFound();
        $fields = $this->getJson("/api/v1/imports/products/fields?menu_id={$menu->id}")->assertOk()->json('body');
        $keys = array_column($fields, 'key');
        foreach ([
            'special_price', 'special_price_type', 'special_price_start', 'special_price_end',
            'new_from', 'new_to', 'category_slugs', 'tax_ids',
        ] as $key) {
            $this->assertContains($key, $keys);
        }
        foreach (['inventory_consumption_mode', 'produced_ingredient_id', 'produced_ingredient_quantity'] as $key) {
            $this->assertNotContains($key, $keys);
        }
        $this->assertSame('date', collect($fields)->firstWhere('key', 'special_price_start')['type']);
        $this->assertTrue(collect($fields)->firstWhere('key', 'category_slugs')['multiple']);
        $this->assertSame(
            [['value' => 'drinks', 'label' => 'Drinks']],
            collect($fields)->firstWhere('key', 'category_slugs')['options']
        );
        $this->assertSame(
            [['value' => (string) $tax->id, 'label' => "Sales tax (#{$tax->id})"]],
            collect($fields)->firstWhere('key', 'tax_ids')['options']
        );
        $this->assertSame([$keys], (new ImportTemplate($fields))->headings());
        $this->get('/api/v1/imports/products/template')->assertOk();
    }

    public function test_product_import_rejects_cross_menu_and_branch_references(): void
    {
        $branch = Branch::factory()->create();
        $otherBranch = Branch::factory()->create();
        $menu = Menu::factory()->create(['branch_id' => $branch->id]);
        $otherMenu = Menu::factory()->create(['branch_id' => $otherBranch->id]);
        Category::query()->create([
            'menu_id' => $otherMenu->id, 'name' => ['en' => 'Other'], 'slug' => 'other', 'is_active' => true,
        ]);
        $otherTax = Tax::query()->create([
            'branch_id' => $otherBranch->id, 'name' => ['en' => 'Other tax'], 'code' => 'OTHER',
            'rate' => 5, 'type' => 'exclusive', 'is_global' => false, 'is_active' => true,
        ]);
        $this->actingAs($this->actor(['admin.products.import', 'admin.products.create'], $branch->id), 'api');
        $import = new DataImport(['context' => ['menu_id' => $menu->id], 'mode' => ImportMode::Upsert]);
        $result = app(ProductImportDefinition::class)->validate([
            'name' => 'Tea', 'price' => '10', 'is_active' => '1',
            'category_slugs' => 'other', 'tax_ids' => (string) $otherTax->id,
        ], $import);

        foreach (['category_slugs', 'tax_ids'] as $field) {
            $this->assertArrayHasKey($field, $result['errors']);
        }

        $legacyImport = new DataImport([
            'context' => ['menu_id' => $menu->id],
            'mode' => ImportMode::Upsert,
            'headers' => ['name', 'Tax Codes'],
        ]);
        $legacy = app(ProductImportDefinition::class)->validate([
            'name' => 'Tea', 'price' => '10', 'is_active' => '1',
        ], $legacyImport);
        $this->assertArrayHasKey('tax_ids', $legacy['errors']);
        $legacyMapped = app(ProductImportDefinition::class)->validate([
            'name' => 'Tea', 'price' => '10', 'is_active' => '1', 'tax_codes' => 'OTHER',
        ], $import);
        $this->assertArrayHasKey('tax_ids', $legacyMapped['errors']);
    }

    public function test_product_import_preserves_unmapped_values_and_clears_mapped_blank_values_on_replace(): void
    {
        $menu = Menu::factory()->create();
        $this->actingAs($this->actor(['admin.products.import', 'admin.products.edit']), 'api');
        $product = Product::factory()->create([
            'menu_id' => $menu->id, 'sku' => 'TEA', 'name' => ['en' => 'Tea'],
            'price' => 10, 'special_price' => 5, 'special_price_type' => 'fixed',
        ]);
        $category = Category::query()->create([
            'menu_id' => $menu->id, 'name' => ['en' => 'Drinks'], 'slug' => 'drinks', 'is_active' => true,
        ]);
        $tax = Tax::query()->create([
            'branch_id' => $menu->branch_id, 'name' => ['en' => 'Sales tax'], 'code' => 'TAX1',
            'rate' => 5, 'type' => 'exclusive', 'is_global' => false, 'is_active' => true,
        ]);
        $product->categories()->attach($category);
        $product->taxes()->attach($tax);
        $definition = app(ProductImportDefinition::class);
        $row = [
            'id' => $product->id, 'name' => 'Tea updated', 'price' => '10', 'is_active' => '1',
            'special_price' => '', 'category_slugs' => '', 'tax_ids' => '',
        ];
        $import = new DataImport(['context' => ['menu_id' => $menu->id], 'mode' => ImportMode::Upsert]);
        $upsert = $definition->validate($row, $import);
        $this->assertSame([], $upsert['errors']);
        $definition->apply($upsert['data'], $import, $product->id);
        $this->assertEquals(5, $product->fresh()->special_price);
        $this->assertTrue($product->fresh()->categories()->whereKey($category->id)->exists());
        $this->assertTrue($product->fresh()->taxes()->whereKey($tax->id)->exists());

        $import->mode = ImportMode::ReplaceExisting;
        $replace = $definition->validate($row, $import);
        $this->assertSame([], $replace['errors']);
        $definition->apply($replace['data'], $import, $product->id);
        $this->assertNull($product->fresh()->special_price);
        $this->assertSame('fixed', $product->fresh()->special_price_type->value);
        $this->assertFalse($product->fresh()->categories()->exists());
        $this->assertFalse($product->fresh()->taxes()->exists());
    }

    public function test_product_import_preserves_inventory_settings_and_validates_pricing_dates(): void
    {
        $branch = Branch::factory()->create();
        $menu = Menu::factory()->create(['branch_id' => $branch->id]);
        $unit = Unit::query()->create([
            'name' => ['en' => 'Gram'], 'symbol' => ['en' => 'g'], 'type' => 'mass',
        ]);
        $ingredient = Ingredient::query()->create([
            'branch_id' => $branch->id, 'unit_id' => $unit->id, 'name' => ['en' => 'Prepared tea'],
            'cost_per_unit' => 1, 'alert_quantity' => 0, 'current_stock' => 10, 'is_returnable' => false,
        ]);
        $this->actingAs($this->actor(['admin.products.import', 'admin.products.edit'], $branch->id), 'api');
        $product = Product::factory()->create([
            'menu_id' => $menu->id, 'sku' => 'PREPARED-TEA', 'name' => ['en' => 'Prepared tea'],
            'price' => 10, 'special_price' => null, 'inventory_consumption_mode' => 'production_output',
            'produced_ingredient_id' => $ingredient->id, 'produced_ingredient_quantity' => 2.5,
        ]);
        $definition = app(ProductImportDefinition::class);
        $import = new DataImport(['context' => ['menu_id' => $menu->id], 'mode' => ImportMode::ReplaceExisting]);
        $row = [
            'sku' => 'PREPARED-TEA', 'name' => 'Prepared tea', 'price' => '10', 'is_active' => '1',
        ];

        $invalid = $definition->validate($row + [
            'special_price' => '11', 'special_price_type' => 'fixed',
            'special_price_start' => '2026-10-31', 'special_price_end' => '2026-10-01',
            'new_from' => '2026-10-31', 'new_to' => '2026-10-01',
        ], $import);
        foreach (['special_price', 'special_price_end', 'new_to'] as $field) {
            $this->assertArrayHasKey($field, $invalid['errors']);
        }

        $valid = $definition->validate($row, $import);
        $this->assertSame([], $valid['errors']);
        $definition->apply($valid['data'], $import, $product->id);
        $this->assertSame('production_output', $product->fresh()->inventory_consumption_mode->value);
        $this->assertSame($ingredient->id, $product->fresh()->produced_ingredient_id);
        $this->assertEquals(2.5, $product->fresh()->produced_ingredient_quantity);
    }

    public function test_create_only_ignores_update_ids_and_exposes_valid_preview_rows(): void
    {
        $branch = Branch::factory()->create();
        $actor = $this->actor(['admin.menus.import', 'admin.menus.create'], $branch->id);
        $existing = Menu::factory()->create(['branch_id' => $branch->id]);
        $this->actingAs($actor, 'api');

        $id = $this->postJson('/api/v1/imports/menus', [
            'file' => UploadedFile::fake()->createWithContent(
                'menus.csv',
                "ID,Name,Active\n{$existing->id},New Breakfast,1\n999999,New Dinner,0\n"
            ),
        ])->assertCreated()->json('body.id');
        $this->postJson("/api/v1/imports/$id/mapping", [
            'mapping' => ['id' => 0, 'name' => 1, 'is_active' => 2],
        ])->assertOk();
        $this->stage($id);
        $this->actingAs($actor, 'api');

        $this->postJson("/api/v1/imports/$id/validate", ['mode' => 'create_only'])->assertOk();
        (new ValidateImportJob($id, DataImport::findOrFail($id)->revision))->handle(app(ImportDefinitionResolver::class), app(ErrorWorkbookWriter::class));
        $this->actingAs($actor, 'api');

        $this->getJson("/api/v1/imports/$id")
            ->assertJsonPath('body.status.id', 'ready')
            ->assertJsonPath('body.valid_rows', 2);
        $this->getJson("/api/v1/imports/$id/rows")
            ->assertJsonCount(2, 'body.data')
            ->assertJsonPath('body.data.0.data.id', null)
            ->assertJsonPath('body.data.0.operation.id', 'create');

        $this->postJson("/api/v1/imports/$id/queue")->assertOk();
        (new ApplyImportJob($id, DataImport::findOrFail($id)->revision))->handle(app(ImportDefinitionResolver::class));
        (new FinalizeImportJob($id, DataImport::findOrFail($id)->revision))->handle(app(ImportDefinitionResolver::class));

        $this->assertSame(2, DataImport::findOrFail($id)->imported_rows);
        $this->assertDatabaseHas('menus', ['id' => $existing->id]);
    }

    public function test_upload_maps_locale_headers_with_mixed_case_keys(): void
    {
        app()->instance('setting', new class
        {
            public function get(string $key, mixed $default = null): mixed
            {
                return match ($key) {
                    'default_locale' => 'en',
                    'supported_locales' => ['en', 'ar_DZ'],
                    default => $default,
                };
            }
        });
        $branch = Branch::factory()->create();
        $actor = $this->actor(['admin.menus.import'], $branch->id);
        $this->actingAs($actor, 'api');

        $this->postJson('/api/v1/imports/menus', [
            'file' => UploadedFile::fake()->createWithContent(
                'menus.csv',
                "Name,Name_ar_DZ,Active\nDinner,عشاء,1\n"
            ),
        ])->assertCreated()
            ->assertJsonPath('body.mapping.name_ar_DZ', 1);
    }

    public function test_import_requires_its_own_permission_and_resolves_the_deferred_service(): void
    {
        $branch = Branch::factory()->create();
        $actor = $this->actor(['admin.menus.create', 'admin.menus.edit'], $branch->id);
        $this->actingAs($actor, 'api');

        $this->getJson('/api/v1/imports/menus/fields')->assertForbidden();
        $this->get('/api/v1/imports/menus/template')->assertForbidden();
        $this->postJson('/api/v1/imports/menus', [
            'file' => UploadedFile::fake()->createWithContent('menus.csv', "Name,Active\nDinner,1\n"),
        ])->assertForbidden();
        $this->assertInstanceOf(ImportServiceInterface::class, app(ImportServiceInterface::class));

        Permission::query()->firstOrCreate(['name' => 'admin.menus.import', 'guard_name' => 'api']);
        $actor->givePermissionTo('admin.menus.import');
        $this->getJson('/api/v1/imports/menus/fields')->assertOk();
        $this->get('/api/v1/imports/menus/template')->assertOk();

        $categoryActor = $this->actor(['admin.categories.create', 'admin.categories.edit'], $branch->id);
        $this->actingAs($categoryActor, 'api');
        $this->getJson('/api/v1/imports/categories/fields')->assertForbidden();

        Permission::query()->firstOrCreate(['name' => 'admin.categories.import', 'guard_name' => 'api']);
        $categoryActor->givePermissionTo('admin.categories.import');
        $this->getJson('/api/v1/imports/categories/fields')->assertOk();
    }

    public function test_mapping_requires_only_mandatory_fields(): void
    {
        $branch = Branch::factory()->create();
        $actor = $this->actor(['admin.menus.import'], $branch->id);
        $this->actingAs($actor, 'api');

        $id = $this->postJson('/api/v1/imports/menus', [
            'file' => UploadedFile::fake()->createWithContent('menus.csv', "Name,Active\nDinner,1\n"),
        ])->assertCreated()->json('body.id');

        $this->postJson("/api/v1/imports/$id/mapping", [
            'mapping' => ['name' => 0],
        ])->assertUnprocessable();

        $this->postJson("/api/v1/imports/$id/mapping", [
            'mapping' => ['name' => 0, 'is_active' => 1],
        ])->assertOk();
        $this->assertSame(['name' => 0, 'is_active' => 1], DataImport::findOrFail($id)->mapping);
    }

    public function test_category_validation_marks_missing_parent_and_downloads_annotated_workbook(): void
    {
        $branch = Branch::factory()->create();
        $actor = $this->actor(['admin.categories.import', 'admin.categories.create', 'admin.categories.edit'], $branch->id);
        $this->actingAs($actor, 'api');
        $menu = Menu::factory()->create(['branch_id' => $branch->id]);
        $id = $this->postJson('/api/v1/imports/categories', [
            'file' => UploadedFile::fake()->createWithContent('categories.csv', "Slug,Name,,Parent Slug,Active\ndrinks,Drinks,unchanged,missing,1\n"),
            'context' => ['menu_id' => $menu->id],
        ])->assertCreated()->json('body.id');
        $this->postJson("/api/v1/imports/$id/mapping", [
            'mapping' => ['slug' => 0, 'name' => 1, 'parent_slug' => 3, 'is_active' => 4],
        ])->assertOk();
        $this->stage($id);
        DataImport::findOrFail($id)->update(['locale' => 'ar']);
        $this->actingAs($actor, 'api');
        $this->postJson("/api/v1/imports/$id/validate", ['mode' => 'create_only'])->assertOk();
        (new ValidateImportJob($id, DataImport::findOrFail($id)->revision))->handle(app(ImportDefinitionResolver::class), app(ErrorWorkbookWriter::class));
        $this->actingAs($actor, 'api');

        $import = DataImport::findOrFail($id);
        $this->assertSame(ImportStatus::ValidationFailed, $import->status);
        $this->assertSame(1, $import->invalid_rows);
        $this->postJson("/api/v1/imports/$id/queue")->assertStatus(409);
        $this->get("/api/v1/imports/$id/errors")->assertOk()->assertDownload('validation-errors-categories.xlsx');
        $workbook = IOFactory::load(Storage::disk('local')->path($import->error_path));
        $this->assertSame('', $workbook->getActiveSheet()->getCell('C1')->getFormattedValue());
        $this->assertSame('unchanged', $workbook->getActiveSheet()->getCell('C2')->getFormattedValue());
        $this->assertSame('Parent Slug', $workbook->getActiveSheet()->getCell('D1')->getFormattedValue());
        $this->assertSame('missing', $workbook->getActiveSheet()->getCell('D2')->getFormattedValue());
        $this->assertSame('solid', $workbook->getActiveSheet()->getCell('D2')->getStyle()->getFill()->getFillType());
        $this->assertSame('أخطاء التحقق', $workbook->getSheet(1)->getTitle());
        $note = $workbook->getActiveSheet()->getComment('D2');
        $this->assertSame(implode("\n", $import->rows()->firstOrFail()->errors['parent_slug']), $note->getText()->getPlainText());
        $this->assertFalse($note->getVisible());
        $this->assertArrayNotHasKey('C2', $workbook->getActiveSheet()->getComments());
        $this->assertSame('en', locale());
        $this->assertSame(0, Category::query()
            ->where('menu_id', $menu->id)
            ->count());
    }

    public function test_category_validation_rejects_a_child_of_an_invalid_imported_parent(): void
    {
        $branch = Branch::factory()->create();
        $actor = $this->actor(['admin.categories.import', 'admin.categories.create'], $branch->id);
        $this->actingAs($actor, 'api');
        $menu = Menu::factory()->create(['branch_id' => $branch->id]);
        $id = $this->postJson('/api/v1/imports/categories', [
            'file' => UploadedFile::fake()->createWithContent('categories.csv', "Slug,Name,Parent Slug,Active\nchild,Child,parent,1\nparent,, ,1\n"),
            'context' => ['menu_id' => $menu->id],
        ])->assertCreated()->json('body.id');
        $this->postJson("/api/v1/imports/$id/mapping", [
            'mapping' => ['slug' => 0, 'name' => 1, 'parent_slug' => 2, 'is_active' => 3],
        ])->assertOk();
        $this->stage($id);
        $this->actingAs($actor, 'api');
        $this->postJson("/api/v1/imports/$id/validate", ['mode' => 'create_only'])->assertOk();
        (new ValidateImportJob($id, DataImport::findOrFail($id)->revision))->handle(app(ImportDefinitionResolver::class), app(ErrorWorkbookWriter::class));

        $import = DataImport::findOrFail($id);
        $this->assertSame(ImportStatus::ValidationFailed, $import->status);
        $this->assertSame(2, $import->invalid_rows);
        $this->assertSame(0, $import->valid_rows);
        $invalidParent = $import->rows()
            ->where('row_number', 2)
            ->firstOrFail();
        $this->assertArrayHasKey('parent_slug', $invalidParent->errors);
    }

    public function test_import_history_and_files_are_user_scoped(): void
    {
        $actor = $this->actor(['admin.menus.import', 'admin.menus.create']);
        $other = $this->actor(['admin.menus.import', 'admin.menus.create']);
        $this->actingAs($actor, 'api');
        $id = $this->postJson('/api/v1/imports/menus', [
            'file' => UploadedFile::fake()->createWithContent('menus.csv', "Name,Branch ID,Active\nExample,1,1\n"),
        ])->assertCreated()->json('body.id');

        $this->actingAs($other, 'api');
        $this->getJson("/api/v1/imports/$id")->assertNotFound();
        $this->get("/api/v1/imports/$id/download")->assertNotFound();
        $this->getJson('/api/v1/imports?resource=menus')->assertJsonCount(0, 'body.data');
    }

    public function test_upload_and_mapping_reject_unrecognized_fields(): void
    {
        $branch = Branch::factory()->create();
        $actor = $this->actor(['admin.menus.import', 'admin.menus.create'], $branch->id);
        $this->actingAs($actor, 'api');

        $file = UploadedFile::fake()->createWithContent('menus.csv', "Name,Active\nDinner,1\n");
        $this->postJson('/api/v1/imports/menus', [
            'file' => $file,
            'context' => ['unexpected' => 1],
        ])->assertUnprocessable();

        $id = $this->postJson('/api/v1/imports/menus', [
            'file' => UploadedFile::fake()->createWithContent('menus.csv', "Name,Active\nDinner,1\n"),
        ])->assertCreated()->json('body.id');

        $this->postJson("/api/v1/imports/$id/mapping", [
            'mapping' => ['name' => 0, 'is_active' => 1, 'unexpected' => 0],
        ])->assertUnprocessable();
    }

    public function test_preview_pagination_accepts_only_a_bounded_page_size(): void
    {
        $branch = Branch::factory()->create();
        $actor = $this->actor(['admin.menus.import', 'admin.menus.create'], $branch->id);
        $this->actingAs($actor, 'api');

        $id = $this->postJson('/api/v1/imports/menus', [
            'file' => UploadedFile::fake()->createWithContent('menus.csv', "Name,Active\nDinner,1\n"),
        ])->assertCreated()->json('body.id');

        $this->getJson("/api/v1/imports/$id/rows?per_page=0")->assertUnprocessable();
        $this->getJson("/api/v1/imports/$id/rows?per_page=101")->assertUnprocessable();
        $this->getJson("/api/v1/imports/$id/rows?per_page=20")
            ->assertOk()
            ->assertJsonPath('body.pagination.per_page', 20);
    }

    public function test_category_import_creates_a_hierarchy_and_edited_preview_value_is_applied(): void
    {
        $branch = Branch::factory()->create();
        $actor = $this->actor(['admin.categories.import', 'admin.categories.create'], $branch->id);
        $this->actingAs($actor, 'api');
        $menu = Menu::factory()->create(['branch_id' => $branch->id]);
        $id = $this->postJson('/api/v1/imports/categories', [
            'file' => UploadedFile::fake()->createWithContent('categories.csv', implode("\n", [
                'Slug,Name,Name Ar,Parent Slug,Active',
                'child,Child,طفل,parent,1',
                'parent,Parent,أصل,,1',
                '',
            ])),
            'context' => ['menu_id' => $menu->id],
        ])->assertCreated()->json('body.id');
        $this->postJson("/api/v1/imports/$id/mapping", [
            'mapping' => ['slug' => 0, 'name' => 1, 'name_ar' => 2, 'parent_slug' => 3, 'is_active' => 4],
        ])->assertOk();
        $this->stage($id);
        $this->actingAs($actor, 'api');

        $childRow = DataImport::findOrFail($id)
            ->rows()
            ->where('row_number', 2)
            ->firstOrFail();
        $this->patchJson("/api/v1/imports/$id/rows/$childRow->id", [
            'data' => ['name' => 'Edited child'],
        ])->assertOk();
        $this->postJson("/api/v1/imports/$id/validate", ['mode' => 'create_only'])->assertOk();
        (new ValidateImportJob($id, DataImport::findOrFail($id)->revision))->handle(app(ImportDefinitionResolver::class), app(ErrorWorkbookWriter::class));
        $this->assertSame(ImportStatus::Ready, DataImport::findOrFail($id)->status);
        $this->actingAs($actor, 'api');
        $this->postJson("/api/v1/imports/$id/queue")->assertOk();
        (new ApplyImportJob($id, DataImport::findOrFail($id)->revision))->handle(app(ImportDefinitionResolver::class));
        (new FinalizeImportJob($id, DataImport::findOrFail($id)->revision))->handle(app(ImportDefinitionResolver::class));

        $child = Category::query()
            ->withoutGlobalActive()
            ->where('menu_id', $menu->id)
            ->where('slug', 'child')
            ->firstOrFail();
        $parent = Category::query()
            ->withoutGlobalActive()
            ->where('menu_id', $menu->id)
            ->where('slug', 'parent')
            ->firstOrFail();
        $this->assertSame('Edited child', $child->getTranslation('name', 'en'));
        $this->assertSame('طفل', $child->getTranslation('name', 'ar'));
        $this->assertSame($parent->id, $child->parent_id);
        $this->assertTrue($child->isDescendantOf($parent));
        $this->assertSame(ImportStatus::Completed, DataImport::findOrFail($id)->status);
    }

    public function test_category_upload_rejects_a_menu_outside_the_assigned_branch(): void
    {
        $ownBranch = Branch::factory()->create();
        $otherBranch = Branch::factory()->create();
        $actor = $this->actor(['admin.categories.import', 'admin.categories.create'], $ownBranch->id);
        $menu = Menu::factory()->create(['branch_id' => $otherBranch->id]);
        $this->actingAs($actor, 'api');

        $this->postJson('/api/v1/imports/categories', [
            'file' => UploadedFile::fake()->createWithContent('categories.csv', "Slug,Name,Active\nsecret,Secret,1\n"),
            'context' => ['menu_id' => $menu->id],
        ])->assertNotFound();
        $this->assertSame(0, DataImport::query()->count());
    }

    public function test_large_file_staging_dispatches_queue_chunks(): void
    {
        $branch = Branch::factory()->create();
        $actor = $this->actor(['admin.menus.import', 'admin.menus.create'], $branch->id);
        $this->actingAs($actor, 'api');
        $id = $this->postJson('/api/v1/imports/menus', [
            'file' => UploadedFile::fake()->createWithContent('menus.csv', "Name,Active\nA,1\n"),
        ])->assertCreated()->json('body.id');
        $this->postJson("/api/v1/imports/$id/mapping", [
            'mapping' => ['name' => 0, 'is_active' => 1],
        ])->assertOk();

        (new StageImportJob($id, 1))->handle(app(ImportDefinitionResolver::class));

        Queue::assertPushed(QueueImport::class);
        $this->assertSame(ImportStatus::Staging, DataImport::findOrFail($id)->status);
    }

    public function test_queued_chunk_reader_finishes_staging(): void
    {
        $branch = Branch::factory()->create();
        $actor = $this->actor(['admin.menus.import', 'admin.menus.create'], $branch->id);
        $this->actingAs($actor, 'api');
        $id = $this->postJson('/api/v1/imports/menus', [
            'file' => UploadedFile::fake()->createWithContent('menus.csv', "Name,Active\nOne,1\nTwo,0\n"),
        ])->assertCreated()->json('body.id');
        $this->postJson("/api/v1/imports/$id/mapping", [
            'mapping' => ['name' => 0, 'is_active' => 1],
        ])->assertOk();
        $this->stage($id);

        $import = DataImport::findOrFail($id);
        $this->assertSame(ImportStatus::Staged, $import->status);
        $this->assertSame(2, $import->total_rows);
    }

    public function test_validation_continues_in_bounded_batches(): void
    {
        $branch = Branch::factory()->create();
        $actor = $this->actor(['admin.menus.import', 'admin.menus.create'], $branch->id);
        $this->actingAs($actor, 'api');
        $csv = "Name,Active\n";
        for ($number = 1; $number <= 201; $number++) {
            $csv .= "Menu $number,0\n";
        }
        $id = $this->postJson('/api/v1/imports/menus', [
            'file' => UploadedFile::fake()->createWithContent('menus.csv', $csv),
        ])->assertCreated()->json('body.id');
        $this->postJson("/api/v1/imports/$id/mapping", [
            'mapping' => ['name' => 0, 'is_active' => 1],
        ])->assertOk();
        $this->stage($id);
        $this->actingAs($actor, 'api');
        $this->postJson("/api/v1/imports/$id/validate", ['mode' => 'create_only'])->assertOk();

        (new ValidateImportJob($id, DataImport::findOrFail($id)->revision))->handle(app(ImportDefinitionResolver::class), app(ErrorWorkbookWriter::class));
        $import = DataImport::findOrFail($id);
        $this->assertSame(ImportStatus::Validating, $import->status);
        $this->assertSame(200, $import->processed_rows);
        $lastId = $import->rows()
            ->orderByDesc('id')
            ->skip(1)
            ->firstOrFail()
            ->id;
        (new ValidateImportJob($id, DataImport::findOrFail($id)->revision, $lastId))->handle(app(ImportDefinitionResolver::class), app(ErrorWorkbookWriter::class));
        $this->assertSame(ImportStatus::Ready, $import->refresh()->status);
        $this->assertSame(201, $import->valid_rows);
    }

    public function test_expired_files_are_removed_while_history_is_retained(): void
    {
        $actor = $this->actor(['admin.menus.import', 'admin.menus.create']);
        $this->actingAs($actor, 'api');
        $id = $this->postJson('/api/v1/imports/menus', [
            'file' => UploadedFile::fake()->createWithContent('menus.csv', "Name,Branch ID,Active\nExample,1,1\n"),
        ])->assertCreated()->json('body.id');
        $import = DataImport::findOrFail($id);
        $path = $import->path;
        Storage::disk('local')->assertExists($path);
        $import->update(['status' => ImportStatus::Completed, 'finished_at' => now()->subDays(31)]);

        $this->artisan('imports:cleanup')->assertExitCode(0);

        Storage::disk('local')->assertMissing($path);
        $this->assertSame('', $import->refresh()->path);
        $this->getJson('/api/v1/imports?resource=menus')
            ->assertJsonCount(1, 'body.data')
            ->assertJsonPath('body.data.0.has_original_file', false);
    }

    public function test_skip_duplicates_rechecks_records_at_import_time(): void
    {
        $branch = Branch::factory()->create();
        $actor = $this->actor(['admin.menus.import', 'admin.menus.create'], $branch->id);
        $this->actingAs($actor, 'api');
        $id = $this->postJson('/api/v1/imports/menus', [
            'file' => UploadedFile::fake()->createWithContent('menus.csv', "Name,Active\nDinner,0\n"),
        ])->assertCreated()->json('body.id');
        $this->postJson("/api/v1/imports/$id/mapping", [
            'mapping' => ['name' => 0, 'is_active' => 1],
        ])->assertOk();
        $this->stage($id);
        $this->actingAs($actor, 'api');
        $this->postJson("/api/v1/imports/$id/validate", ['mode' => 'skip_duplicates'])->assertOk();
        (new ValidateImportJob($id, DataImport::findOrFail($id)->revision))->handle(app(ImportDefinitionResolver::class), app(ErrorWorkbookWriter::class));
        $this->actingAs($actor, 'api');
        Menu::factory()->create(['branch_id' => $branch->id, 'name' => ['en' => 'Dinner']]);

        $this->postJson("/api/v1/imports/$id/queue")->assertOk();
        (new ApplyImportJob($id, DataImport::findOrFail($id)->revision))->handle(app(ImportDefinitionResolver::class));
        (new FinalizeImportJob($id, DataImport::findOrFail($id)->revision))->handle(app(ImportDefinitionResolver::class));

        $import = DataImport::findOrFail($id);
        $this->assertSame(0, $import->imported_rows);
        $this->assertSame(1, $import->skipped_rows);
        $this->assertSame(ImportStatus::Completed, $import->status);
        $this->assertSame(1, Menu::query()
            ->withoutGlobalActive()
            ->where('branch_id', $branch->id)
            ->where('name->en', 'Dinner')
            ->count());
    }

    public function test_menu_validation_marks_the_matching_field_and_preserves_missing_id_errors(): void
    {
        $branch = Branch::factory()->create();
        $otherBranch = Branch::factory()->create();
        $this->actingAs($this->actor(['admin.menus.import', 'admin.menus.create', 'admin.menus.edit']), 'api');
        $existing = Menu::factory()->create(['branch_id' => $branch->id, 'name' => ['en' => 'Lunch']]);
        $definition = app(MenuImportDefinition::class);
        $import = new DataImport(['mode' => ImportMode::CreateOnly]);
        $data = ['id' => $existing->id, 'name' => 'Lunch', 'branch_id' => $branch->id, 'is_active' => 'yes'];
        $result = $definition->validate($data, $import);
        $this->assertSame(['name' => [__('menu::menus.import.exists')]], $result['errors']);
        $this->assertNull($result['data']['id']);
        $result = $definition->validate(array_replace($data, ['branch_id' => $otherBranch->id]), $import);
        $this->assertSame([], $result['errors']);
        $this->assertSame(ImportOperation::Create, $result['operation']);
        $import->mode = ImportMode::UpdateOnly;
        $result = $definition->validate(array_replace($data, ['id' => 999999]), $import);
        $this->assertSame(['id' => [__('menu::menus.import.missing_id')]], $result['errors']);
    }

    public function test_menu_branch_choices_are_limited_to_the_assigned_branch(): void
    {
        $branch = Branch::factory()->create();
        Branch::factory()->create();
        $this->actingAs($this->actor(['admin.menus.import'], $branch->id), 'api');
        $fields = $this->getJson('/api/v1/imports/menus/fields')->assertOk()->json('body');
        $options = collect($fields)->firstWhere('key', 'branch_id')['options'];
        $this->assertSame([['value' => (string) $branch->id, 'label' => $branch->name]], $options);
    }

    public function test_apply_reconciles_committed_rows_and_retried_failures(): void
    {
        $branch = Branch::factory()->create();
        $this->actingAs($this->actor(['admin.menus.import', 'admin.menus.create'], $branch->id), 'api');
        $id = $this->postJson('/api/v1/imports/menus', [
            'file' => UploadedFile::fake()->createWithContent('menus.csv', "Name,Active\nSaved,1\nRetry,1\n"),
        ])->assertCreated()->json('body.id');
        $import = DataImport::findOrFail($id);
        $import->update(['status' => ImportStatus::Queued, 'total_rows' => 2, 'valid_rows' => 2, 'imported_rows' => 0]);
        $saved = $import->rows()->create([
            'row_number' => 2, 'values' => ['Saved', '1'], 'data' => ['name' => 'Saved', 'is_active' => '1'],
            'status' => ImportRowStatus::Processed, 'operation' => ImportOperation::Create,
        ]);
        $import->rows()->create([
            'row_number' => 3, 'values' => ['Retry', '1'], 'data' => ['name' => 'Retry', 'is_active' => '1'],
            'status' => ImportRowStatus::Failed, 'operation' => ImportOperation::Create,
        ]);
        (new ApplyImportJob($id, $import->revision))->handle(app(ImportDefinitionResolver::class));
        $this->assertSame(2, $import->refresh()->imported_rows);
        $this->assertSame(2, $import->processed_rows);
        $this->assertSame(0, $import->failed_rows);
        $this->assertSame(ImportRowStatus::Processed, $saved->refresh()->status);
        $this->assertSame(1, Menu::query()->withoutGlobalActive()->where('branch_id', $branch->id)->count());
    }

    public function test_replayed_and_stale_validation_jobs_do_not_change_counts(): void
    {
        config()->set('import.validation_batch_size', 1);
        $branch = Branch::factory()->create();
        $actor = $this->actor(['admin.menus.import', 'admin.menus.create'], $branch->id);
        $this->actingAs($actor, 'api');
        $id = $this->postJson('/api/v1/imports/menus', [
            'file' => UploadedFile::fake()->createWithContent('menus.csv', "name,is_active\nOne,1\nTwo,1\n"),
        ])->assertCreated()->json('body.id');
        $this->postJson("/api/v1/imports/$id/mapping", ['mapping' => ['name' => 0, 'is_active' => 1]])->assertOk();
        $this->stage($id);
        $this->actingAs($actor, 'api');
        $this->postJson("/api/v1/imports/$id/validate", ['mode' => 'create_only'])->assertOk();
        $import = DataImport::findOrFail($id);
        $job = new ValidateImportJob($id, $import->revision);
        $job->handle(app(ImportDefinitionResolver::class), app(ErrorWorkbookWriter::class));
        $job->handle(app(ImportDefinitionResolver::class), app(ErrorWorkbookWriter::class));
        $this->assertSame(1, $import->refresh()->valid_rows);
        $this->assertSame(1, $import->processed_rows);
        $job->failed(new \RuntimeException('Worker timed out'));
        $this->assertSame(ImportStatus::Failed, $import->refresh()->status);
        $this->actingAs($actor, 'api');
        $this->postJson("/api/v1/imports/$id/retry")->assertOk();
        $this->assertSame(0, $import->refresh()->valid_rows);
        $job->handle(app(ImportDefinitionResolver::class), app(ErrorWorkbookWriter::class));
        $job->failed(new \RuntimeException('Old worker timed out'));
        $this->assertSame(ImportStatus::Validating, $import->refresh()->status);
        $this->assertSame(0, $import->valid_rows);
    }

    public function test_excel_headers_and_rows_use_only_the_first_worksheet(): void
    {
        $branch = Branch::factory()->create();
        $actor = $this->actor(['admin.menus.import', 'admin.menus.create'], $branch->id);
        foreach (['xlsx' => \PhpOffice\PhpSpreadsheet\Writer\Xlsx::class, 'xls' => \PhpOffice\PhpSpreadsheet\Writer\Xls::class] as $extension => $writer) {
            $this->actingAs($actor, 'api');
            $book = new \PhpOffice\PhpSpreadsheet\Spreadsheet;
            $book->getActiveSheet()->setTitle('Menus')->fromArray([['name', 'is_active'], ['Dinner', '1']]);
            $book->createSheet()->setTitle('Notes')->fromArray([['message', 'number'], ['Ignored', '1'], ['Not a menu', '1']]);
            $book->setActiveSheetIndex(1);
            $path = tempnam(sys_get_temp_dir(), 'import-sheets-');
            try {
                (new $writer($book))->save($path);
                $id = $this->postJson('/api/v1/imports/menus', [
                    'file' => new UploadedFile($path, "menus.$extension", null, null, true),
                ])->assertCreated()->assertJsonPath('body.headers', ['name', 'is_active'])->json('body.id');
                $this->postJson("/api/v1/imports/$id/mapping", ['mapping' => ['name' => 0, 'is_active' => 1]])->assertOk();
                $this->stage($id);
                $import = DataImport::findOrFail($id);
                $this->assertSame(1, $import->total_rows);
                $this->assertSame('Dinner', $import->rows()->firstOrFail()->data['name']);
            } finally {
                $book->disconnectWorksheets();
                @unlink($path);
            }
        }
    }

    public function test_large_category_import_advances_in_bounded_sync_requests(): void
    {
        $branch = Branch::factory()->create();
        $actor = $this->actor(['admin.categories.import', 'admin.categories.create'], $branch->id);
        $this->actingAs($actor, 'api');
        $menu = Menu::factory()->create(['branch_id' => $branch->id]);
        $csv = "slug,name,parent_slug,is_active\n";
        for ($i = 0; $i < 2275; $i++) {
            $parent = $i % 13 === 0 ? '' : 'category-'.($i - $i % 13);
            $csv .= "category-$i,Category $i,$parent,1\n";
        }
        Queue::swap(Queue::getFacadeRoot()->queue);
        $id = $this->postJson('/api/v1/imports/categories', [
            'file' => UploadedFile::fake()->createWithContent('categories.csv', $csv),
            'context' => ['menu_id' => $menu->id],
        ])->assertCreated()->json('body.id');
        $this->postJson("/api/v1/imports/$id/mapping", ['mapping' => ['slug' => 0, 'name' => 1, 'parent_slug' => 2, 'is_active' => 3]])->assertOk();
        $this->actingAs($actor, 'api');
        $this->postJson("/api/v1/imports/$id/validate", ['mode' => 'create_only'], ['X-Import-Batches' => '1'])
            ->assertOk()->assertJsonPath('body.requires_processing', true)->assertJsonPath('body.processed_rows', 0);
        $import = DataImport::findOrFail($id);
        $requests = 0;
        while ($import->status === ImportStatus::Validating && $requests++ < 60) {
            $before = $import->processed_rows;
            $this->actingAs($actor, 'api');
            $this->postJson("/api/v1/imports/$id/advance")->assertOk();
            $import->refresh();
            $this->assertLessThanOrEqual(200, $import->processed_rows - $before);
        }
        $this->assertSame(ImportStatus::Ready, $import->status);
        $this->assertSame(2275, $import->valid_rows);
        $this->actingAs($actor, 'api');
        $this->postJson("/api/v1/imports/$id/queue", [], ['X-Import-Batches' => '1'])
            ->assertOk()->assertJsonPath('body.requires_processing', true)->assertJsonPath('body.imported_rows', 0);
        $import->refresh();
        $requests = 0;
        while ($import->status !== ImportStatus::Completed && $requests++ < 60) {
            $before = $import->imported_rows;
            $this->actingAs($actor, 'api');
            $this->postJson("/api/v1/imports/$id/advance")->assertOk();
            $import->refresh();
            $this->assertLessThanOrEqual(100, $import->imported_rows - $before);
        }
        $this->assertSame(ImportStatus::Completed, $import->status);
        $this->assertSame(2275, $import->imported_rows);
        $this->assertNull($import->continuation);
        $this->assertSame(2275, Category::query()->withoutGlobalActive()->where('menu_id', $menu->id)->count());
        $child = Category::query()->withoutGlobalActive()->where('menu_id', $menu->id)->where('slug', 'category-2274')->firstOrFail();
        $parent = Category::query()->withoutGlobalActive()->where('menu_id', $menu->id)->where('slug', 'category-2262')->firstOrFail();
        $this->assertSame($parent->id, $child->parent_id);
        $this->assertTrue($child->isDescendantOf($parent));
        $this->actingAs($actor, 'api');
        $this->postJson("/api/v1/imports/$id/advance")->assertOk()->assertJsonPath('body.imported_rows', 2275);
        $this->assertSame('sync', config('queue.default'));
        $this->actingAs($this->actor(['admin.categories.import'], $branch->id), 'api');
        $this->postJson("/api/v1/imports/$id/advance")->assertNotFound();
    }

    public function test_expired_failed_imports_cannot_be_retried(): void
    {
        $actor = $this->actor(['admin.menus.import']);
        $this->actingAs($actor, 'api');
        $id = $this->postJson('/api/v1/imports/menus', [
            'file' => UploadedFile::fake()->createWithContent('menus.csv', "name,is_active\nDinner,1\n"),
        ])->assertCreated()->json('body.id');
        DataImport::findOrFail($id)->update(['status' => ImportStatus::Failed, 'path' => '']);
        $this->postJson("/api/v1/imports/$id/retry")->assertConflict();
    }

    public function test_failed_browser_batch_rolls_back_rows_and_retries_validation(): void
    {
        $branch = Branch::factory()->create();
        $actor = $this->actor(['admin.menus.import', 'admin.menus.create'], $branch->id);
        $this->actingAs($actor, 'api');
        Queue::swap(Queue::getFacadeRoot()->queue);
        $id = $this->postJson('/api/v1/imports/menus', [
            'file' => UploadedFile::fake()->createWithContent('menus.csv', "name,is_active\nDinner,invalid\n"),
        ])->assertCreated()->json('body.id');
        $this->postJson("/api/v1/imports/$id/mapping", ['mapping' => ['name' => 0, 'is_active' => 1]])->assertOk();
        $this->actingAs($actor, 'api');
        $this->postJson("/api/v1/imports/$id/validate", ['mode' => 'create_only'], ['X-Import-Batches' => '1'])->assertOk();
        $this->mock(ErrorWorkbookWriter::class, fn ($mock) => $mock->shouldReceive('write')->once()->andThrow(new \RuntimeException('Storage unavailable')));
        $this->postJson("/api/v1/imports/$id/advance")->assertStatus(500);
        $import = DataImport::findOrFail($id);
        $this->assertSame(ImportStatus::Failed, $import->status);
        $this->assertSame(0, $import->processed_rows);
        $this->assertSame(ImportRowStatus::Pending, $import->rows()->firstOrFail()->status);
        $this->assertNotNull($import->continuation);
        $this->app->forgetInstance(ErrorWorkbookWriter::class);
        $this->actingAs($actor, 'api');
        $this->postJson("/api/v1/imports/$id/retry", [], ['X-Import-Batches' => '1'])->assertOk()
            ->assertJsonPath('body.status.id', 'validating');
        $this->postJson("/api/v1/imports/$id/advance")->assertOk()->assertJsonPath('body.status.id', 'validation_failed');
        $this->assertSame(0, Menu::query()->withoutGlobalActive()->where('branch_id', $branch->id)->count());
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

    private function stage(int $id): void
    {
        $fake = Queue::getFacadeRoot();
        Queue::swap($fake->queue);
        try {
            (new StageImportJob($id, 1))->handle(app(ImportDefinitionResolver::class));
        } finally {
            Queue::swap($fake);
        }
    }
}
