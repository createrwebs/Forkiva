<?php

namespace Modules\User\Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Branch\Models\Branch;
use Modules\Delivery\Models\DeliveryDriver;
use Modules\Hr\Models\Employee;
use Modules\User\Models\Role;
use Modules\User\Models\User;
use Modules\User\Services\Auth\AuthServiceInterface;
use Modules\User\Services\TwoFactor\TwoFactorServiceInterface;
use Nwidart\Modules\Facades\Module;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

class AuthIdentityTest extends TestCase
{
    use RefreshDatabase;

    public function test_login_accepts_normalized_email_and_username_including_email_shaped_usernames(): void
    {
        $user = $this->user(['username' => 'Login.Alias@example.com', 'email' => 'Stored.Contact@example.com']);
        foreach ([' LOGIN.ALIAS@EXAMPLE.COM ', ' stored.contact@example.com '] as $identifier) {
            $this->postJson('/api/v1/auth/login', ['identifier' => $identifier, 'password' => 'password'])
                ->assertOk()->assertJsonPath('body.user.id', $user->id);
        }
        $this->assertSame(2, $user->tokens()->count());
    }

    public function test_case_and_whitespace_variants_share_the_login_attempt_limit(): void
    {
        $user = $this->user(['username' => 'Attempt.Owner']);
        foreach (['Attempt.Owner', 'attempt.owner', 'ATTEMPT.OWNER'] as $identifier) {
            $this->postJson('/api/v1/auth/login', ['identifier' => $identifier, 'password' => 'wrong-password'])
                ->assertUnauthorized();
        }
        $this->postJson('/api/v1/auth/login', ['identifier' => ' attempt.owner ', 'password' => 'password'])
            ->assertStatus(429);
        $this->assertSame(0, $user->tokens()->count());
    }

    public static function collisionStates(): array
    {
        return [['active'], ['inactive'], ['deleted']];
    }

    #[DataProvider('collisionStates')]
    public function test_ambiguous_identifier_never_authenticates_either_account(string $state): void
    {
        $one = $this->user(['email' => 'shared@example.com']);
        $two = $this->user(['username' => 'SHARED@EXAMPLE.COM', 'is_active' => $state !== 'inactive']);
        if ($state === 'deleted') {
            $two->delete();
        }
        $this->postJson('/api/v1/auth/login', ['identifier' => 'shared@example.com', 'password' => 'password'])
            ->assertUnauthorized()->assertJsonPath('message', __('auth.failed'));
        $this->assertSame(0, $one->tokens()->count());
        $this->assertSame(0, $two->tokens()->count());
    }

    public function test_employee_login_accepts_code_or_id_but_never_a_truncated_numeric_identifier(): void
    {
        [$user, $employee] = $this->employee();
        foreach ([$employee->id.'junk', $employee->id.'.5', '99999999999999999999999999999'] as $identifier) {
            $this->postJson('/api/v1/auth/login', ['identifier' => $identifier, 'password' => 'password', 'employee' => true])
                ->assertUnauthorized();
        }
        $this->assertSame(0, $user->tokens()->count());
        foreach ([' staff-code ', (string) $employee->id] as $identifier) {
            $this->postJson('/api/v1/auth/login', ['identifier' => $identifier, 'password' => 'password', 'employee' => true])
                ->assertOk()->assertJsonPath('body.user.id', $user->id);
        }
    }

    public function test_employee_identifiers_do_not_work_for_regular_login_or_override_another_username(): void
    {
        [$owner, $employee] = $this->employee();
        $this->postJson('/api/v1/auth/login', ['identifier' => $employee->code, 'password' => 'password'])->assertUnauthorized();
        $other = $this->user(['username' => $employee->code]);
        $this->postJson('/api/v1/auth/login', ['identifier' => $employee->code, 'password' => 'password', 'employee' => true])
            ->assertUnauthorized()->assertJsonPath('message', __('auth.failed'));
        $this->assertSame(0, $owner->tokens()->count());
        $this->assertSame(0, $other->tokens()->count());
    }

    public static function mobileModes(): array
    {
        return [['employee', false], ['driver', false], ['employee', true], ['driver', true]];
    }

    #[DataProvider('mobileModes')]
    public function test_mobile_login_rejects_missing_or_disabled_modules(string $mode, bool $disabled): void
    {
        [$user, $employee] = $this->employee();
        DeliveryDriver::query()->create([
            'employee_id' => $employee->id, 'branch_id' => $employee->branch_id,
            'status' => 'available', 'is_active' => true,
        ]);
        $service = app(AuthServiceInterface::class);
        $module = $disabled ? \Mockery::mock(\Nwidart\Modules\Module::class) : null;
        $module?->shouldReceive('isEnabled')->andReturn(false);
        Module::shouldReceive('find')->andReturn($module);

        try {
            $service->login(['identifier' => $user->email, 'password' => 'password', $mode => true]);
            $this->fail('A requested mobile context must not fall back to regular login.');
        } catch (HttpException $exception) {
            $this->assertSame(401, $exception->getStatusCode());
            $this->assertSame(0, $user->tokens()->count());
        }
    }

    public function test_inactive_employee_is_rejected_even_with_the_correct_password(): void
    {
        [$user, $employee] = $this->employee();
        $employee->forceFill(['is_active' => false])->save();
        $this->postJson('/api/v1/auth/login', ['identifier' => $user->email, 'password' => 'password', 'employee' => true])
            ->assertUnauthorized();
        $this->assertSame(0, $user->tokens()->count());
    }

    public function test_two_factor_completion_rechecks_employee_status_before_issuing_a_token(): void
    {
        [$user, $employee] = $this->employee();
        $employee->forceFill(['is_active' => false])->save();
        $this->mock(TwoFactorServiceInterface::class, function ($mock) use ($user) {
            $mock->shouldReceive('verifyLoginChallenge')->once()->andReturn([
                'user' => $user->fresh(), 'context' => ['employee' => true],
            ]);
        });
        try {
            app(AuthServiceInterface::class)->completeTwoFactorLogin('challenge', '123456');
            $this->fail('An inactive employee must not receive a token after two-factor verification.');
        } catch (HttpException $exception) {
            $this->assertSame(401, $exception->getStatusCode());
            $this->assertSame(0, $user->tokens()->count());
        }
    }

    public function test_driver_login_requires_delivery_even_when_hr_is_enabled(): void
    {
        $user = $this->user();
        $service = app(AuthServiceInterface::class);
        $hr = Module::find('Hr');
        Module::shouldReceive('find')->with('Hr')->andReturn($hr);
        Module::shouldReceive('find')->with('Delivery')->andReturn(null);
        Module::shouldReceive('find')->with('delivery')->andReturn(null);
        try {
            $service->login(['identifier' => $user->email, 'password' => 'password', 'driver' => true]);
            $this->fail('Driver login requires the delivery module.');
        } catch (HttpException $exception) {
            $this->assertSame(401, $exception->getStatusCode());
            $this->assertSame(0, $user->tokens()->count());
        }
    }

    public function test_two_factor_completion_rechecks_required_modules(): void
    {
        [$user] = $this->employee();
        $this->mock(TwoFactorServiceInterface::class, function ($mock) use ($user) {
            $mock->shouldReceive('verifyLoginChallenge')->once()->andReturn([
                'user' => $user, 'context' => ['employee' => true],
            ]);
        });
        $service = app(AuthServiceInterface::class);
        Module::shouldReceive('find')->andReturn(null);
        try {
            $service->completeTwoFactorLogin('challenge', '123456');
            $this->fail('A missing module must prevent completing a mobile login.');
        } catch (HttpException $exception) {
            $this->assertSame(401, $exception->getStatusCode());
            $this->assertSame(0, $user->tokens()->count());
        }
    }

    private function user(array $attributes = []): User
    {
        $user = User::factory()->create($attributes + ['password' => 'password', 'is_active' => true]);
        $user->assignRole(Role::query()->firstOrCreate(
            ['name' => 'auth_identity_test', 'guard_name' => 'api'], ['display_name' => 'Auth Identity Test'],
        ));

        return $user;
    }

    private function employee(): array
    {
        $branch = Branch::factory()->create(['currency' => 'JOD']);
        $user = $this->user();
        $employee = Employee::factory()->forBranch($branch->id)->create([
            'user_id' => $user->id, 'code' => 'STAFF-CODE', 'is_active' => true,
        ]);

        return [$user, $employee];
    }
}
