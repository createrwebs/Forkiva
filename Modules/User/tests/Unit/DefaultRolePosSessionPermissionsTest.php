<?php

namespace Modules\User\Tests\Unit;

use Modules\User\Enums\DefaultRole;
use Modules\User\Facades\Permission;
use Tests\TestCase;

class DefaultRolePosSessionPermissionsTest extends TestCase
{
    public function test_supervisor_roles_receive_the_registered_permission_but_cashier_roles_do_not(): void
    {
        $this->assertContains('admin.pos_sessions.close_others', Permission::getPermissionNames());
        foreach ([DefaultRole::AdminBranch, DefaultRole::Manager] as $role) {
            $this->assertContains('admin.pos_sessions.close', $role->getPermissions());
            $this->assertContains('admin.pos_sessions.close_others', $role->getPermissions());
        }
        foreach ([DefaultRole::Cashier, DefaultRole::Waiter, DefaultRole::Kitchen] as $role) {
            $this->assertNotContains('admin.pos_sessions.close_others', $role->getPermissions());
        }
        $this->assertSame('*', DefaultRole::Admin->getPermissions());
    }
}
