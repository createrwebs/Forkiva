<?php

namespace Modules\User\Console\Permission;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Modules\User\Enums\DefaultRole;
use Modules\User\Facades\Permission;
use Modules\User\Models\Role;
use Nwidart\Modules\Facades\Module;
use Symfony\Component\Console\Input\InputOption;

class SyncDefaultRoles extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $name = 'permission:sync-default-roles';

    /**
     * The console command description.
     */
    protected $description = 'Sync default roles with database.';

    /**
     * Execute the console command.
     */
    public function handle(): void
    {
        if ($this->option('force')) {
            $this->forceCreateOrUpdate();
        } else {
            $this->createMissingRoles();
        }

        $this->syncPermissions();
    }

    /**
     * Force sync default roles, even if they already exist
     */
    private function forceCreateOrUpdate(): void
    {
        foreach (DefaultRole::cases() as $case) {
            if ($this->shouldSkipRole($case->value)) {
                continue;
            }
            Role::updateOrCreate(
                attributes: [
                    'name' => $case->value,
                ],
                values: [
                    'built_in' => true,
                    'display_name' => $case->allTrans(),
                ]
            );
        }
    }

    /**
     * Create only the missing default rules
     */
    private function createMissingRoles(): void
    {
        $roles = array_diff(
            DefaultRole::values(),
            DB::table((new Role)->getTable())->pluck('name')->toArray()
        );

        foreach ($roles as $role) {
            if ($this->shouldSkipRole($role)) {
                continue;
            }
            Role::create([
                'name' => $role,
                'built_in' => true,
                'display_name' => DefaultRole::tryFrom($role)->allTrans(),
            ]);
        }
    }

    /**
     * Roles sync permissions
     */
    public function syncPermissions(): void
    {
        $allPermission = Permission::getPermissionNames();

        $roles = Role::whereIn('name', DefaultRole::values())
            ->whereNot('name', DefaultRole::SuperAdmin->value)
            ->get();

        /** @var Role $role */
        foreach ($roles as $role) {
            if ($this->shouldSkipRole($role->name)) {
                continue;
            }
            $rolePermission = DefaultRole::from($role->name)->getPermissions();
            $role->syncPermissions($rolePermission === '*' ? $allPermission : $this->filterRegisteredPermissions($rolePermission, $allPermission));
        }
    }

    /**
     * Keep role permission sync aligned with currently registered module permissions.
     */
    private function filterRegisteredPermissions(array $rolePermissions, array $registeredPermissions): array
    {
        return array_values(array_intersect($rolePermissions, $registeredPermissions));
    }

    /**
     * Get the console command options.
     */
    public function getOptions(): array
    {
        return [
            ['force', 'f', InputOption::VALUE_NONE, 'Force sync default roles, even if they already exist.'],
        ];
    }

    /**
     * Determine whether one built-in role depends on a disabled module.
     */
    private function shouldSkipRole(string $role): bool
    {
        if (
            in_array($role, [
                DefaultRole::HrOfficer->value,
                DefaultRole::HrManager->value,
                DefaultRole::Staff->value,
                DefaultRole::Payroll->value,
            ], true)
            && ! Module::find('hr')
        ) {
            return true;
        }

        if (
            in_array($role, [
                DefaultRole::Driver->value,
                DefaultRole::Dispatcher->value,
                DefaultRole::ExternalCourierOperator->value,
            ], true)
            && ! Module::find('delivery')
        ) {
            return true;
        }

        if (
            in_array($role, [
                DefaultRole::Accountant->value,
                DefaultRole::AccountingManager->value,
                DefaultRole::AccountingAuditor->value,
            ], true)
            && ! Module::find('accounting')
        ) {
            return true;
        }

        return false;
    }
}
