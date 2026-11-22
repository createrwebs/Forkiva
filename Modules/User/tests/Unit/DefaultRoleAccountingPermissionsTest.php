<?php

namespace Modules\User\Tests\Unit;

use Modules\User\Enums\DefaultRole;
use Modules\User\Facades\Permission;
use Tests\TestCase;

class DefaultRoleAccountingPermissionsTest extends TestCase
{
    public function test_accounting_manager_receives_the_complete_accounting_permission_catalog(): void
    {
        $registeredAccountingPermissions = Permission::getPermissionNames(
            Permission::all()->where('module', 'accounting'),
        );

        $this->assertEqualsCanonicalizing(
            [...$registeredAccountingPermissions, 'admin.daily_finance.index', 'admin.profiles.edit'],
            DefaultRole::AccountingManager->getPermissions(),
        );
    }

    public function test_accountant_can_prepare_daily_work_without_control_or_configuration_writes(): void
    {
        $permissions = DefaultRole::Accountant->getPermissions();

        $this->assertContains('admin.accounting_journal_entries.create', $permissions);
        $this->assertContains('admin.accounting_supplier_bills.create', $permissions);
        $this->assertContains('admin.accounting_reconciliations.match', $permissions);
        $this->assertContains('admin.accounting_reports.export', $permissions);
        $this->assertNotContains('admin.accounting_journal_entries.post', $permissions);
        $this->assertNotContains('admin.accounting_reconciliations.approve', $permissions);
        $this->assertNotContains('admin.accounting_settings.edit', $permissions);
        $this->assertNotContains('admin.accounting_periods.lock', $permissions);
        $this->assertNotContains('admin.accounting_year_end_closes.create', $permissions);
    }

    public function test_accounting_auditor_has_read_and_export_permissions_only(): void
    {
        $permissions = DefaultRole::AccountingAuditor->getPermissions();
        $readActions = ['index', 'show', 'export', 'download'];

        foreach ($permissions as $permission) {
            if ($permission === 'admin.profiles.edit') {
                continue;
            }

            $this->assertContains(last(explode('.', $permission)), $readActions, $permission);
        }

        $this->assertContains('admin.accounting_dashboard.index', $permissions);
        $this->assertContains('admin.accounting_reports.export', $permissions);
        $this->assertNotContains('admin.accounting_journal_entries.create', $permissions);
    }
}
