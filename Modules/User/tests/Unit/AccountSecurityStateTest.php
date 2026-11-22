<?php

namespace Modules\User\Tests\Unit;

use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Laravel\Sanctum\PersonalAccessToken;
use Modules\User\Models\User;
use Modules\User\Providers\DeferredUserServiceProvider;
use Modules\User\Services\Auth\AccountSecurityState;
use Modules\User\Services\Auth\AccountSecurityStateInterface;
use Tests\TestCase;

class AccountSecurityStateTest extends TestCase
{
    public function test_interface_is_provided_as_a_deferred_singleton(): void
    {
        $provider = new DeferredUserServiceProvider($this->app);
        $this->assertTrue($provider->isDeferred());
        $this->assertContains(AccountSecurityStateInterface::class, $provider->provides());

        $state = app(AccountSecurityStateInterface::class);
        $this->assertInstanceOf(AccountSecurityState::class, $state);
        $this->assertSame($state, app(AccountSecurityStateInterface::class));
    }

    public function test_fingerprint_preserves_the_existing_security_state_format(): void
    {
        $attributes = [
            'password' => 'fixture-password-hash',
            'email' => 'fixture@example.test',
            'phone' => '+12025550100',
            'phone_country_iso_code' => 'US',
            'two_factor_method' => 'authenticator',
            'two_factor_secret' => 'fixture-secret-ciphertext',
            'two_factor_recovery_codes' => 'fixture-recovery-ciphertext',
            'two_factor_confirmed_at' => '2026-08-29 00:00:00',
        ];
        $user = new User;
        $user->setRawAttributes($attributes, true);
        // The existing fingerprint uses the model's formatted phone, not its raw storage value.
        $attributes['phone'] = '+1 202-555-0100';
        $expected = hash_hmac('sha256', serialize(array_values($attributes)), config('app.key'));

        $this->assertSame($expected, app(AccountSecurityStateInterface::class)->fingerprint($user));
    }

    public function test_bearer_scope_uses_the_current_access_token(): void
    {
        $user = new User;
        $user->withAccessToken((new PersonalAccessToken)->forceFill(['id' => 41]));

        $this->assertSame('token:41', app(AccountSecurityStateInterface::class)->sessionScope($user));
    }

    public function test_stateful_scope_hashes_the_current_session_identifier(): void
    {
        $request = Request::create('/');
        $session = app('session')->driver();
        $session->setId(str_repeat('a', 40));
        $request->setLaravelSession($session);
        $this->app->instance('request', $request);

        $this->assertSame(
            'session:'.hash('sha256', $session->getId()),
            app(AccountSecurityStateInterface::class)->sessionScope(new User),
        );
    }

    public function test_missing_session_and_token_are_rejected(): void
    {
        $this->app->instance('request', Request::create('/'));
        $this->expectException(ValidationException::class);

        app(AccountSecurityStateInterface::class)->sessionScope(new User);
    }
}
