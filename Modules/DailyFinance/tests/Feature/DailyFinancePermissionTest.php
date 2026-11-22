<?php

namespace Modules\DailyFinance\Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\User\Enums\DefaultRole;
use Modules\User\Facades\Permission as PermissionCatalog;
use Modules\User\Models\Permission;
use Modules\User\Models\Role;
use Tests\TestCase;

class DailyFinancePermissionTest extends TestCase
{
    use RefreshDatabase;

    public function test_module_permission_is_registered_translated_and_synced_without_changing_custom_roles(): void
    {
        $this->assertSame('dailyfinance', PermissionCatalog::get('daily_finance')['module']);
        $this->assertSame('Daily finance', trans('dailyfinance::permissions.daily_finance', [], 'en'));
        $this->assertSame('المالية اليومية', trans('dailyfinance::permissions.daily_finance', [], 'ar'));
        $this->artisan('permission:sync-permissions')->assertExitCode(0);

        $custom = Role::create(['name' => 'daily_finance_custom', 'display_name' => ['en' => 'Custom'], 'built_in' => false]);
        $custom->givePermissionTo('admin.payments.index');
        $this->artisan('permission:sync-default-roles')->assertExitCode(0);
        $this->artisan('permission:sync-permissions')->assertExitCode(0);

        foreach ([DefaultRole::Admin, DefaultRole::AdminBranch, DefaultRole::Manager, DefaultRole::Accountant, DefaultRole::AccountingManager, DefaultRole::AccountingAuditor] as $role) {
            $this->assertTrue(Role::findByName($role->value)->hasPermissionTo('admin.daily_finance.index'));
        }
        foreach ([DefaultRole::Cashier, DefaultRole::Waiter, DefaultRole::Kitchen] as $role) {
            $this->assertFalse(Role::findByName($role->value)->hasPermissionTo('admin.daily_finance.index'));
        }

        foreach ([DefaultRole::Admin, DefaultRole::AdminBranch, DefaultRole::Manager] as $role) {
            foreach (['index', 'create', 'receive', 'cancel'] as $action) {
                $this->assertTrue(Role::findByName($role->value)->hasPermissionTo('admin.daily_finance_handovers.'.$action));
            }
        }
        $this->assertSame('dailyfinance', PermissionCatalog::get('daily_finance_handovers')['module']);
        $this->assertSame('dailyfinance', PermissionCatalog::get('daily_finance_closings')['module']);
        $this->assertSame('dailyfinance', PermissionCatalog::get('daily_finance_settlements')['module']);
        foreach ([DefaultRole::Admin, DefaultRole::AdminBranch, DefaultRole::Manager] as $role) {
            foreach (['index', 'create', 'receive', 'cancel'] as $action) {
                $this->assertTrue(Role::findByName($role->value)->hasPermissionTo('admin.daily_finance_settlements.'.$action));
            }
        }
        foreach ([DefaultRole::Admin, DefaultRole::AdminBranch, DefaultRole::Manager] as $role) {
            foreach (['index', 'close'] as $action) {
                $this->assertTrue(Role::findByName($role->value)->hasPermissionTo('admin.daily_finance_closings.'.$action));
            }
        }

        $this->assertSame(1, Permission::where('name', 'admin.daily_finance.index')->count());
        $this->assertSame(['admin.payments.index'], $custom->fresh()->permissions->pluck('name')->all());
    }
}
