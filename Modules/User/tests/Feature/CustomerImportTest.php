<?php

namespace Modules\User\Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Modules\Import\Enums\ImportMode;
use Modules\Import\Enums\ImportOperation;
use Modules\Import\Jobs\ApplyImportJob;
use Modules\Import\Jobs\FinalizeImportJob;
use Modules\Import\Jobs\StageImportJob;
use Modules\Import\Jobs\ValidateImportJob;
use Modules\Import\Models\DataImport;
use Modules\Import\Support\ErrorWorkbookWriter;
use Modules\Import\Support\ImportDefinitionResolver;
use Modules\User\Enums\DefaultRole;
use Modules\User\Enums\TwoFactorMethod;
use Modules\User\Models\Permission;
use Modules\User\Models\Role;
use Modules\User\Models\User;
use PhpOffice\PhpSpreadsheet\Shared\Date as ExcelDate;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Tests\TestCase;

class CustomerImportTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('import.disk', 'local');
        Storage::fake('local');
        Queue::fake();
        Role::query()->firstOrCreate(
            ['name' => DefaultRole::Customer->value, 'guard_name' => 'api'],
            ['display_name' => ['en' => 'Customer']]
        );
    }

    public function test_import_only_permission_can_create_customer_from_csv(): void
    {
        $actor = $this->actor();
        $this->actingAs($actor, 'api');
        $this->getJson('/api/v1/imports/customers/fields')->assertOk()
            ->assertJsonFragment(['key' => 'phone', 'required' => true])
            ->assertJsonFragment(['key' => 'birthdate', 'type' => 'date'])
            ->assertJsonFragment(['value' => 'JO', 'label' => 'Jordan']);
        $id = $this->postJson('/api/v1/imports/customers', [
            'file' => UploadedFile::fake()->createWithContent(
                'customers.csv',
                "Name,Phone Country ISO Code,Phone,Customer Type,Is Active,Email\nSam,JO,+962791234567,regular,1,sam@example.com\n"
            ),
        ])->assertCreated()->json('body.id');
        $this->postJson("/api/v1/imports/$id/mapping", [
            'mapping' => ['name' => 0, 'phone_country_iso_code' => 1, 'phone' => 2, 'customer_type' => 3, 'is_active' => 4, 'email' => 5],
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

        $customer = User::query()->role(DefaultRole::Customer)->firstOrFail();
        $this->assertSame('+962791234567', $customer->getRawOriginal('phone'));
        $this->assertNull($customer->password);
        $this->assertSame('sam@example.com', $customer->email);
    }

    public function test_import_matches_normalized_phone_and_preserves_blank_optional_fields(): void
    {
        $this->actingAs($this->actor(), 'api');
        $definition = app(ImportDefinitionResolver::class)->resolve('customers');
        $import = new DataImport(['mode' => ImportMode::Upsert, 'context' => []]);
        $first = ['name' => 'Sam', 'phone_country_iso_code' => 'JO', 'phone' => '+962791234567', 'customer_type' => 'regular', 'is_active' => '1', 'email' => 'sam@example.com'];
        $result = $definition->validate($first, $import);
        $this->assertSame([], $result['errors']);
        $definition->apply($result['data'], $import, $result['target_id']);

        $second = [...$first, 'phone' => '0791234567', 'name' => 'Samuel', 'email' => ''];
        $result = $definition->validate($second, $import);
        $this->assertSame(ImportOperation::Update, $result['operation']);
        $this->assertSame([], $result['errors']);
        $this->assertSame($definition->key($first, $import), $definition->key($second, $import));
        $definition->apply($result['data'], $import, $result['target_id']);
        $customer = User::query()->role(DefaultRole::Customer)->firstOrFail();
        $this->assertSame('Samuel', $customer->name);
        $this->assertSame('sam@example.com', $customer->email);
        $this->assertSame(1, User::query()->role(DefaultRole::Customer)->count());
    }

    public function test_excel_birthdate_serial_is_normalized_before_validation(): void
    {
        $actor = $this->actor();
        $this->actingAs($actor, 'api');
        $book = new Spreadsheet;
        $book->getActiveSheet()->fromArray([
            ['name', 'phone_country_iso_code', 'phone', 'customer_type', 'is_active', 'birthdate'],
            ['Sam', 'JO', '+962791234567', 'regular', 1, 35005],
        ]);
        $book->getActiveSheet()->getStyle('F2')->getNumberFormat()->setFormatCode('yyyy-mm-dd');
        $path = tempnam(sys_get_temp_dir(), 'customer-import-');
        try {
            (new Xlsx($book))->save($path);
            $id = $this->postJson('/api/v1/imports/customers', [
                'file' => new UploadedFile($path, 'customers.xlsx', null, null, true),
            ])->assertCreated()->json('body.id');
            $this->postJson("/api/v1/imports/$id/mapping", [
                'mapping' => ['name' => 0, 'phone_country_iso_code' => 1, 'phone' => 2, 'customer_type' => 3, 'is_active' => 4, 'birthdate' => 5],
            ])->assertOk();
            $fake = Queue::getFacadeRoot();
            Queue::swap($fake->queue);
            try {
                (new StageImportJob($id, 1))->handle(app(ImportDefinitionResolver::class));
            } finally {
                Queue::swap($fake);
            }
            $this->assertSame('1995-11-02', DataImport::findOrFail($id)->rows()->firstOrFail()->data['birthdate']);

            $this->actingAs($actor, 'api');
            $this->postJson("/api/v1/imports/$id/validate", ['mode' => 'create_only'])->assertOk();
            (new ValidateImportJob($id, DataImport::findOrFail($id)->revision))
                ->handle(app(ImportDefinitionResolver::class), app(ErrorWorkbookWriter::class));
            $import = DataImport::findOrFail($id);
            $this->assertSame(1, $import->valid_rows);
            $this->assertSame('1995-11-02', $import->rows()->firstOrFail()->data['birthdate']);

            $legacyData = $import->rows()->firstOrFail()->data;
            $legacyData['birthdate'] = '35005';
            $this->assertSame('1995-11-02', app(ImportDefinitionResolver::class)
                ->resolve('customers')->validate($legacyData, $import)['data']['birthdate']);

            $this->actingAs($actor, 'api');
            $this->postJson("/api/v1/imports/$id/queue")->assertOk();
            (new ApplyImportJob($id, $import->refresh()->revision))->handle(app(ImportDefinitionResolver::class));
            (new FinalizeImportJob($id, $import->refresh()->revision))->handle(app(ImportDefinitionResolver::class));
            $customer = User::query()->role(DefaultRole::Customer)->firstOrFail();
            $this->assertSame('1995-11-02', $customer->birthdate?->format('Y-m-d'));
        } finally {
            $book->disconnectWorksheets();
            @unlink($path);
        }
    }

    public function test_excel_1904_birthdate_is_normalized_using_the_workbook_calendar(): void
    {
        $actor = $this->actor();
        $this->actingAs($actor, 'api');
        $previousCalendar = ExcelDate::getExcelCalendar();
        ExcelDate::setExcelCalendar(ExcelDate::CALENDAR_MAC_1904);
        $book = new Spreadsheet;
        $book->getActiveSheet()->fromArray([
            ['name', 'phone_country_iso_code', 'phone', 'customer_type', 'is_active', 'birthdate'],
            ['Sam', 'JO', '+962791234567', 'regular', 1, 33543],
        ]);
        $book->getActiveSheet()->getStyle('F2')->getNumberFormat()->setFormatCode('yyyy-mm-dd');
        $path = tempnam(sys_get_temp_dir(), 'customer-import-');
        try {
            (new Xlsx($book))->save($path);
            ExcelDate::setExcelCalendar($previousCalendar);
            $id = $this->postJson('/api/v1/imports/customers', [
                'file' => new UploadedFile($path, 'customers.xlsx', null, null, true),
            ])->assertCreated()->json('body.id');
            $this->postJson("/api/v1/imports/$id/mapping", [
                'mapping' => ['name' => 0, 'phone_country_iso_code' => 1, 'phone' => 2, 'customer_type' => 3, 'is_active' => 4, 'birthdate' => 5],
            ])->assertOk();
            $fake = Queue::getFacadeRoot();
            Queue::swap($fake->queue);
            try {
                (new StageImportJob($id, 1))->handle(app(ImportDefinitionResolver::class));
            } finally {
                Queue::swap($fake);
            }
            $this->assertSame('1995-11-02', DataImport::findOrFail($id)->rows()->firstOrFail()->data['birthdate']);
        } finally {
            ExcelDate::setExcelCalendar($previousCalendar);
            $book->disconnectWorksheets();
            @unlink($path);
        }
    }

    public function test_import_rejects_phone_owned_by_non_customer(): void
    {
        $actor = $this->actor();
        $other = User::factory()->create(['phone' => '+962791234568', 'phone_country_iso_code' => 'JO']);
        $this->actingAs($actor, 'api');
        $definition = app(ImportDefinitionResolver::class)->resolve('customers');
        $import = new DataImport(['mode' => ImportMode::Upsert, 'context' => []]);
        $data = ['name' => 'Wrong account', 'phone_country_iso_code' => 'JO', 'phone' => '0791234568', 'customer_type' => 'regular', 'is_active' => '1'];
        $result = $definition->validate($data, $import);

        $this->assertSame(ImportOperation::Create, $result['operation']);
        $this->assertArrayHasKey('phone', $result['errors']);
        $this->assertFalse($other->hasRole(DefaultRole::Customer));
    }

    public function test_import_rejects_usernames_and_emails_owned_by_other_accounts(): void
    {
        $actor = $this->actor();
        User::factory()->create(['username' => 'taken_user', 'email' => 'taken@example.com']);
        $this->actingAs($actor, 'api');
        $definition = app(ImportDefinitionResolver::class)->resolve('customers');
        $import = new DataImport(['mode' => ImportMode::Upsert, 'context' => []]);
        $result = $definition->validate([
            'name' => 'New customer',
            'phone_country_iso_code' => 'JO',
            'phone' => '+962791234569',
            'customer_type' => 'regular',
            'is_active' => '1',
            'username' => 'taken_user',
            'email' => 'taken@example.com',
        ], $import);

        $this->assertArrayHasKey('username', $result['errors']);
        $this->assertArrayHasKey('email', $result['errors']);
    }

    public function test_import_rejects_phone_change_for_sms_enrolled_customer(): void
    {
        $actor = $this->actor();
        $customer = User::factory()->create([
            'phone' => '+962791234567',
            'phone_country_iso_code' => 'JO',
            'two_factor_method' => TwoFactorMethod::Sms,
            'two_factor_confirmed_at' => now(),
        ]);
        $customer->assignRole(DefaultRole::Customer);
        $this->actingAs($actor, 'api');
        $definition = app(ImportDefinitionResolver::class)->resolve('customers');
        $import = new DataImport(['mode' => ImportMode::Upsert, 'context' => []]);
        $result = $definition->validate([
            'id' => $customer->id,
            'name' => 'Updated customer',
            'phone_country_iso_code' => 'JO',
            'phone' => '+962791234569',
            'customer_type' => 'regular',
            'is_active' => '1',
        ], $import);

        $this->assertArrayHasKey('phone', $result['errors']);
    }

    private function actor(): User
    {
        $user = User::factory()->create(['is_active' => true]);
        Permission::query()->firstOrCreate(['name' => 'admin.customers.import', 'guard_name' => 'api']);
        $user->givePermissionTo('admin.customers.import');

        return $user;
    }
}
