<?php

namespace Modules\User\Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
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
use Modules\User\Enums\DefaultRole;
use Modules\User\Enums\TwoFactorMethod;
use Modules\User\Models\Permission;
use Modules\User\Models\Role;
use Modules\User\Models\User;
use Tests\TestCase;

class UserImportTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('import.disk', 'local');
        Storage::fake('local');
        Queue::fake();
        Role::query()->firstOrCreate(
            ['name' => 'operator', 'guard_name' => 'api'],
            ['display_name' => ['en' => 'Operator']]
        );
        Role::query()->firstOrCreate(
            ['name' => DefaultRole::Manager->value, 'guard_name' => 'api'],
            ['display_name' => ['en' => 'Manager']]
        );
        Role::query()->firstOrCreate(
            ['name' => DefaultRole::Customer->value, 'guard_name' => 'api'],
            ['display_name' => ['en' => 'Customer']]
        );
    }

    public function test_import_permission_can_create_user_without_staging_a_password(): void
    {
        $actor = $this->actor();
        $role = Role::query()->where('name', 'operator')->firstOrFail();
        $this->actingAs($actor, 'api');
        $this->getJson('/api/v1/imports/users/fields')->assertOk()
            ->assertJsonFragment(['key' => 'username', 'required' => true])
            ->assertJsonFragment(['value' => (string) $role->id, 'label' => 'Operator']);
        $id = $this->postJson('/api/v1/imports/users', [
            'file' => UploadedFile::fake()->createWithContent(
                'users.csv',
                "name,username,email,phone_country_iso_code,phone,gender,role,is_active\nSam,sam.user,sam@example.com,JO,+962791234567,male,$role->id,1\n"
            ),
        ])->assertCreated()->json('body.id');
        $this->postJson("/api/v1/imports/$id/mapping", [
            'mapping' => ['name' => 0, 'username' => 1, 'email' => 2, 'phone_country_iso_code' => 3, 'phone' => 4, 'gender' => 5, 'role' => 6, 'is_active' => 7],
        ])->assertOk();
        $fake = Queue::getFacadeRoot();
        Queue::swap($fake->queue);
        try {
            (new StageImportJob($id, 1))->handle(app(ImportDefinitionResolver::class));
        } finally {
            Queue::swap($fake);
        }
        $this->assertArrayNotHasKey('password', DataImport::findOrFail($id)->rows()->firstOrFail()->data);
        $this->actingAs($actor, 'api');
        $this->postJson("/api/v1/imports/$id/validate", ['mode' => 'create_only'])->assertOk();
        (new ValidateImportJob($id, DataImport::findOrFail($id)->revision))
            ->handle(app(ImportDefinitionResolver::class), app(ErrorWorkbookWriter::class));
        $this->assertSame(1, DataImport::findOrFail($id)->valid_rows);
        $this->actingAs($actor, 'api');
        $this->postJson("/api/v1/imports/$id/queue")->assertOk();
        (new ApplyImportJob($id, DataImport::findOrFail($id)->revision))->handle(app(ImportDefinitionResolver::class));
        (new FinalizeImportJob($id, DataImport::findOrFail($id)->revision))->handle(app(ImportDefinitionResolver::class));

        $user = User::query()->where('username', 'sam.user')->firstOrFail();
        $this->assertSame('Sam', $user->name);
        $this->assertSame('+962791234567', $user->getRawOriginal('phone'));
        $this->assertTrue($user->hasRole('operator'));
        $this->assertNotNull($user->password);
        $this->assertFalse(Hash::check('password', $user->password));
    }

    public function test_import_restricts_branch_roles_and_preserves_password_on_update(): void
    {
        $branch = Branch::factory()->create();
        $actor = $this->actor(['branch_id' => $branch->id]);
        $manager = Role::query()->where('name', DefaultRole::Manager->value)->firstOrFail();
        $operator = Role::query()->where('name', 'operator')->firstOrFail();
        $this->actingAs($actor, 'api');
        $definition = app(ImportDefinitionResolver::class)->resolve('users');
        $import = new DataImport(['mode' => ImportMode::Upsert, 'context' => ['branch_id' => $branch->id]]);
        $data = $this->row($manager->id, ['branch_id' => 99999]);
        $result = $definition->validate($data, $import);
        $this->assertSame([], $result['errors']);
        $this->assertSame((string) $branch->id, (string) $result['data']['branch_id']);
        $definition->apply($result['data'], $import, null);
        $user = User::query()->where('username', 'sam.user')->firstOrFail();
        $this->assertSame($branch->id, $user->branch_id);
        $originalPassword = $user->password;

        $result = $definition->validate($this->row($manager->id, ['name' => 'Samuel']), $import);
        $this->assertSame(ImportOperation::Update, $result['operation']);
        $definition->apply($result['data'], $import, $result['target_id']);
        $this->assertSame('Samuel', $user->fresh()->name);
        $this->assertSame($originalPassword, $user->fresh()->password);

        $replace = new DataImport(['mode' => ImportMode::ReplaceExisting, 'context' => ['branch_id' => $branch->id]]);
        $this->assertSame([], $definition->validate($this->row($manager->id), $replace)['errors']);

        $invalidRole = $definition->validate($this->row($operator->id), $import);
        $this->assertArrayHasKey('role', $invalidRole['errors']);
    }

    public function test_import_rejects_customer_and_protects_enrolled_contact_details(): void
    {
        $actor = $this->actor();
        $operator = Role::query()->where('name', 'operator')->firstOrFail();
        $customer = User::factory()->create(['username' => 'customer.user']);
        $customer->assignRole(DefaultRole::Customer);
        $user = User::factory()->create([
            'username' => 'sam.user',
            'email' => 'sam@example.com',
            'phone' => '+962791234567',
            'phone_country_iso_code' => 'JO',
            'two_factor_method' => TwoFactorMethod::Email,
            'two_factor_confirmed_at' => now(),
        ]);
        $user->assignRole('operator');
        $this->actingAs($actor, 'api');
        $definition = app(ImportDefinitionResolver::class)->resolve('users');
        $import = new DataImport(['mode' => ImportMode::Upsert, 'context' => ['branch_id' => null]]);

        $wrongAccount = $definition->validate($this->row($operator->id, ['id' => $customer->id]), $import);
        $this->assertArrayHasKey('id', $wrongAccount['errors']);
        $changedEmail = $definition->validate($this->row($operator->id, ['email' => 'changed@example.com']), $import);
        $this->assertArrayHasKey('email', $changedEmail['errors']);
        $user->forceFill(['two_factor_method' => TwoFactorMethod::Sms, 'two_factor_confirmed_at' => now()])->save();
        $changedPhone = $definition->validate($this->row($operator->id, ['phone' => '+962791234568']), $import);
        $this->assertArrayHasKey('phone', $changedPhone['errors']);
        $mainUser = $definition->validate($this->row($operator->id, ['id' => $actor->id]), $import);
        $this->assertArrayHasKey('id', $mainUser['errors']);
        $mainByUsername = $definition->validate($this->row($operator->id, ['username' => $actor->username]), $import);
        $this->assertArrayHasKey('username', $mainByUsername['errors']);
    }

    public function test_replace_mode_cannot_remove_a_required_branch(): void
    {
        $branch = Branch::factory()->create();
        $actor = $this->actor();
        $manager = Role::query()->where('name', DefaultRole::Manager->value)->firstOrFail();
        $user = User::factory()->create([
            'username' => 'sam.user',
            'email' => 'sam@example.com',
            'phone_country_iso_code' => 'JO',
            'phone' => '+962791234567',
            'branch_id' => $branch->id,
        ]);
        $user->assignRole(DefaultRole::Manager);
        $this->actingAs($actor, 'api');
        $definition = app(ImportDefinitionResolver::class)->resolve('users');
        $row = $this->row($manager->id, ['branch_id' => '']);

        $upsert = $definition->validate($row, new DataImport(['mode' => ImportMode::Upsert, 'context' => ['branch_id' => null]]));
        $this->assertSame([], $upsert['errors']);
        $replace = $definition->validate($row, new DataImport(['mode' => ImportMode::ReplaceExisting, 'context' => ['branch_id' => null]]));
        $this->assertArrayHasKey('branch_id', $replace['errors']);
    }

    public function test_import_requires_its_own_permission(): void
    {
        $this->actingAs(User::factory()->create(), 'api');
        $this->getJson('/api/v1/imports/users/fields')->assertForbidden();
    }

    private function actor(array $attributes = []): User
    {
        $user = User::factory()->create(['is_active' => true] + $attributes);
        Permission::query()->firstOrCreate(['name' => 'admin.users.import', 'guard_name' => 'api']);
        $user->givePermissionTo('admin.users.import');

        return $user;
    }

    private function row(int $roleId, array $overrides = []): array
    {
        return array_replace([
            'name' => 'Sam',
            'username' => 'sam.user',
            'email' => 'sam@example.com',
            'phone_country_iso_code' => 'JO',
            'phone' => '+962791234567',
            'gender' => 'male',
            'role' => (string) $roleId,
            'is_active' => '1',
        ], $overrides);
    }
}
