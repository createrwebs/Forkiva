<?php

namespace Modules\User\Tests\Feature;

use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\User\Models\Role;
use Modules\User\Models\User;
use Tests\TestCase;

class AuthTokenSecurityTest extends TestCase
{
    use RefreshDatabase;

    public function test_login_issues_an_expiring_personal_access_token(): void
    {
        config()->set('sanctum.expiration', 60);
        $user = User::factory()->create([
            'password' => 'password',
            'is_active' => true,
        ]);
        $role = Role::query()->create([
            'name' => 'auth_security_test',
            'display_name' => 'Auth Security Test',
            'guard_name' => 'api',
        ]);
        $user->assignRole($role);

        $response = $this->postJson('/api/v1/auth/login', [
            'identifier' => $user->email,
            'password' => 'password',
        ])->assertOk();

        $token = $user->tokens()->sole();
        $this->assertSame(
            $token->expires_at->timestamp,
            Carbon::parse($response->json('body.token_expires_at'))->timestamp,
        );
        $this->assertTrue($token->expires_at->between(now()->addMinutes(59), now()->addMinutes(61)));
    }

    public function test_wrong_password_does_not_disclose_inactive_account_state(): void
    {
        $user = User::factory()->create([
            'password' => 'correct-password',
            'is_active' => false,
        ]);

        $this->postJson('/api/v1/auth/login', [
            'identifier' => $user->email,
            'password' => 'wrong-password',
        ])->assertUnauthorized()->assertJsonPath('message', __('auth.failed'));
    }

    public function test_wrong_password_does_not_disclose_deleted_account_state(): void
    {
        $user = User::factory()->create([
            'password' => 'correct-password',
            'is_active' => true,
        ]);
        $user->delete();

        $this->postJson('/api/v1/auth/login', [
            'identifier' => $user->email,
            'password' => 'wrong-password',
        ])->assertUnauthorized()->assertJsonPath('message', __('auth.failed'));
    }
}
