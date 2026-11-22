<?php

namespace Modules\User\Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\User\Enums\DefaultRole;
use Modules\User\Models\Permission;
use Modules\User\Models\Role;
use Tests\TestCase;

class PosSessionClosingPermissionSyncTest extends TestCase
{
    use RefreshDatabase;

    public function test_standard_bootstrap_registers_delegated_closing_for_supervisors_only(): void
    {
        $this->syncPermissionsAndRoles();

        foreach ([DefaultRole::Admin, DefaultRole::AdminBranch, DefaultRole::Manager] as $role) {
            $stored = Role::findByName($role->value);
            $this->assertTrue($stored->hasPermissionTo('admin.pos_sessions.close'));
            $this->assertTrue($stored->hasPermissionTo('admin.pos_sessions.close_others'));
        }
        foreach ([DefaultRole::Cashier, DefaultRole::Waiter, DefaultRole::Kitchen] as $role) {
            $this->assertFalse(Role::findByName($role->value)->hasPermissionTo('admin.pos_sessions.close_others'));
        }
        $this->assertSame(1, Permission::where('name', 'admin.pos_sessions.close_others')->count());
    }

    public function test_standard_upgrade_restores_default_grants_without_changing_custom_roles_or_duplicating_permissions(): void
    {
        $this->syncPermissionsAndRoles();
        $manager = Role::findByName(DefaultRole::Manager->value);
        $manager->revokePermissionTo('admin.pos_sessions.close_others');
        $custom = Role::create(['name' => 'custom_shift_lead', 'display_name' => ['en' => 'Shift lead'], 'built_in' => false]);
        $custom->givePermissionTo('admin.pos_sessions.show');
        $permissionId = Permission::findByName('admin.pos_sessions.close_others')->id;

        $this->syncPermissionsAndRoles();
        $this->syncPermissionsAndRoles();

        $this->assertSame($permissionId, Permission::findByName('admin.pos_sessions.close_others')->id);
        $this->assertSame(1, Permission::where('name', 'admin.pos_sessions.close_others')->count());
        $this->assertSame($manager->id, Role::findByName(DefaultRole::Manager->value)->id);
        $this->assertTrue($manager->fresh()->hasPermissionTo('admin.pos_sessions.close_others'));
        $this->assertSame(['admin.pos_sessions.show'], $custom->fresh()->permissions->pluck('name')->all());
        $this->assertSame(0, (int) $custom->fresh()->built_in);
    }

    private function syncPermissionsAndRoles(): void
    {
        $this->artisan('permission:sync-permissions')->assertExitCode(0);
        $this->artisan('permission:sync-default-roles')->assertExitCode(0);
    }
}
