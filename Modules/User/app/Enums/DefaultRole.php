<?php

namespace Modules\User\Enums;

use Modules\Support\Traits\EnumArrayable;
use Modules\Support\Traits\EnumTranslatable;

enum DefaultRole: string
{
    use EnumArrayable, EnumTranslatable;

    case SuperAdmin = 'super_admin';
    case Admin = 'admin';
    case AdminBranch = 'admin_branch';
    case Manager = 'manager';
    case Cashier = 'cashier';
    case Kitchen = 'kitchen';
    case Waiter = 'waiter';
    case Customer = 'customer';
    case HrManager = 'hr_manager';
    case HrOfficer = 'hr_officer';
    case Staff = 'staff';
    case Driver = 'driver';
    case Dispatcher = 'dispatcher';
    case ExternalCourierOperator = 'external_courier_operator';
    case Inventory = 'inventory';
    case Payroll = 'payroll';
    case Procurement = 'procurement';
    case Accountant = 'accountant';
    case AccountingManager = 'accounting_manager';
    case AccountingAuditor = 'accounting_auditor';

    /**
     * Get branch available roles
     */
    public static function getBranchAvailableRoles(): array
    {
        return [
            DefaultRole::AdminBranch->value,
            DefaultRole::Manager->value,
            DefaultRole::Waiter->value,
            DefaultRole::Cashier->value,
            DefaultRole::Kitchen->value,
            DefaultRole::Staff->value,
            DefaultRole::Driver->value,
            DefaultRole::Dispatcher->value,
            DefaultRole::ExternalCourierOperator->value,
            DefaultRole::Inventory->value,
            DefaultRole::Payroll->value,
            DefaultRole::Procurement->value,
        ];
    }

    /** {@inheritDoc} */
    public static function getTransKey(): string
    {
        return 'user::enums.default_roles';
    }

    /**
     * Get role permissions
     */
    public function getPermissions(): array|string
    {
        $permissions = require __DIR__.'/../../resources/data/roles_permissions.php';

        return $permissions[$this->value] ?? [];
    }
}
