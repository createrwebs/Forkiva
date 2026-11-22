<?php

namespace Modules\User\Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Notification;
use Illuminate\Validation\ValidationException;
use Laravel\Sanctum\Sanctum;
use Modules\Setting\Repositories\SettingRepository;
use Modules\User\Enums\TwoFactorMethod;
use Modules\User\Models\Role;
use Modules\User\Models\User;
use Modules\User\Notifications\TwoFactorCodeNotification;
use Modules\User\Services\TwoFactor\TwoFactorService;
use ReflectionMethod;
use ReflectionProperty;
use Tests\TestCase;

class TwoFactorAuthTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->freezeSecond();
        config([
            'cache.stores.two_factor_testing' => ['driver' => 'array'],
            'user.two_factor.cache_store' => 'two_factor_testing',
        ]);
        Notification::fake();
        $this->installSettings();
    }

    public function test_password_login_does_not_issue_a_token_until_authenticator_code_is_verified(): void
    {
        $this->installSettings();
        $user = $this->createUser();
        Sanctum::actingAs($user);

        $setup = $this->postJson('/api/v1/accounts/two-factor/setup', [
            'method' => 'authenticator',
            'current_password' => 'password',
        ])->assertOk()
            ->assertJsonPath('body.method', 'authenticator')
            ->assertJsonStructure(['body' => ['secret', 'otpauth_uri']])
            ->json('body');

        $code = $this->totp((string) $setup['secret']);
        Cache::store('array')->flush();

        $confirmation = $this->postJson('/api/v1/accounts/two-factor/confirm', [
            'code' => $code,
        ])->assertOk()
            ->assertJsonPath('body.enabled', true)
            ->assertJsonCount(8, 'body.recovery_codes')
            ->json('body');

        $login = $this->postJson('/api/v1/auth/login', [
            'identifier' => $user->email,
            'password' => 'password',
        ])->assertOk()
            ->assertJsonMissing(['token'])
            ->assertJsonPath('body.two_factor_required', true)
            ->assertJsonPath('body.method', 'authenticator')
            ->json('body');

        $this->postJson('/api/v1/auth/two-factor/verify', [
            'challenge_token' => $login['challenge_token'],
            'code' => $code,
        ])->assertUnprocessable();

        $this->travel(31)->seconds();
        $this->postJson('/api/v1/auth/two-factor/verify', [
            'challenge_token' => $login['challenge_token'],
            'code' => $this->totp((string) $setup['secret']),
        ])->assertOk()
            ->assertJsonPath('body.user.id', $user->id)
            ->assertJsonStructure(['body' => ['token']]);

        $this->assertNotEmpty($confirmation['recovery_codes']);
    }

    public function test_recovery_code_is_consumed_once(): void
    {
        $this->installSettings();
        $user = $this->createUser();
        $recoveryCode = 'ABCDE-12345';
        $user->forceFill([
            'two_factor_method' => 'authenticator',
            'two_factor_secret' => 'JBSWY3DPEHPK3PXP',
            'two_factor_recovery_codes' => [bcrypt($recoveryCode)],
            'two_factor_confirmed_at' => now(),
        ])->save();

        $challenge = $this->postJson('/api/v1/auth/login', [
            'identifier' => $user->email,
            'password' => 'password',
        ])->assertOk()->json('body');

        $this->postJson('/api/v1/auth/two-factor/verify', [
            'challenge_token' => $challenge['challenge_token'],
            'code' => $recoveryCode,
        ])->assertOk();

        $this->assertSame([], $user->refresh()->two_factor_recovery_codes);

        $secondChallenge = $this->postJson('/api/v1/auth/login', [
            'identifier' => $user->email,
            'password' => 'password',
        ])->assertOk()->json('body');

        $this->postJson('/api/v1/auth/two-factor/verify', [
            'challenge_token' => $secondChallenge['challenge_token'],
            'code' => $recoveryCode,
        ])->assertUnprocessable();
    }

    public function test_recovery_code_can_authorize_recovery_code_regeneration(): void
    {
        $this->installSettings();
        $user = $this->createUser();
        $recoveryCode = 'XBLN1-DD6JC';
        $user->forceFill([
            'two_factor_method' => 'authenticator',
            'two_factor_secret' => 'JBSWY3DPEHPK3PXP',
            'two_factor_recovery_codes' => [bcrypt($recoveryCode)],
            'two_factor_confirmed_at' => now(),
        ])->save();
        Sanctum::actingAs($user);

        $newCodes = $this->postJson('/api/v1/accounts/two-factor/recovery-codes', [
            'code' => $recoveryCode,
            'current_password' => 'password',
        ])->assertOk()
            ->assertJsonCount(8, 'body.recovery_codes')
            ->json('body.recovery_codes');

        $this->assertCount(8, $newCodes);
        $this->assertFalse(collect($user->refresh()->two_factor_recovery_codes)->contains(
            fn (string $hash): bool => password_verify($recoveryCode, $hash),
        ));

        $this->postJson('/api/v1/accounts/two-factor/recovery-codes', [
            'code' => $recoveryCode,
            'current_password' => 'password',
        ])->assertUnprocessable();
    }

    public function test_delivered_login_challenge_enforces_and_reports_the_resend_cooldown(): void
    {
        Notification::fake();
        $this->installSettings([
            'two_factor_email_enabled' => true,
            'services.notifications.email.enabled' => true,
            'services.notifications.email.mailer' => 'log',
        ]);
        $user = $this->createUser();
        $user->forceFill([
            'two_factor_method' => 'email',
            'two_factor_recovery_codes' => [bcrypt('ABCDE-12345')],
            'two_factor_confirmed_at' => now(),
        ])->save();

        $challenge = $this->postJson('/api/v1/auth/login', [
            'identifier' => $user->email,
            'password' => 'password',
        ])->assertOk()
            ->assertJsonPath('body.can_resend', true)
            ->assertJsonPath('body.resend_available_in', 60)
            ->json('body');

        $this->postJson('/api/v1/auth/two-factor/resend', [
            'challenge_token' => $challenge['challenge_token'],
        ])->assertTooManyRequests()
            ->assertJsonPath('message', __('user::auth.two_factor_resend_wait'));

        Notification::assertSentToTimes($user, TwoFactorCodeNotification::class, 1);
    }

    public function test_account_code_enforces_and_reports_the_resend_cooldown(): void
    {
        Notification::fake();
        $this->installSettings([
            'two_factor_email_enabled' => true,
            'services.notifications.email.enabled' => true,
            'services.notifications.email.mailer' => 'log',
        ]);
        $user = $this->createUser();
        $user->forceFill([
            'two_factor_method' => 'email',
            'two_factor_recovery_codes' => [bcrypt('ABCDE-12345')],
            'two_factor_confirmed_at' => now(),
        ])->save();
        Sanctum::actingAs($user);

        $this->postJson('/api/v1/accounts/two-factor/code')
            ->assertOk()
            ->assertJsonPath('body.resend_available_in', 60);

        $this->postJson('/api/v1/accounts/two-factor/code')
            ->assertTooManyRequests()
            ->assertJsonPath('message', __('user::auth.two_factor_resend_wait'));

        Notification::assertSentToTimes($user, TwoFactorCodeNotification::class, 1);
    }

    public function test_setup_rejects_disabled_or_unavailable_methods(): void
    {
        $this->installSettings([
            'two_factor_authenticator_enabled' => false,
            'two_factor_email_enabled' => false,
            'two_factor_sms_enabled' => false,
        ]);
        $user = $this->createUser();
        Sanctum::actingAs($user);

        $this->postJson('/api/v1/accounts/two-factor/setup', [
            'method' => 'authenticator',
            'current_password' => 'password',
        ])->assertUnprocessable();
    }

    public function test_email_setup_sends_a_code_when_the_method_is_enabled(): void
    {
        Notification::fake();
        $this->installSettings([
            'two_factor_email_enabled' => true,
            'services.notifications.email.enabled' => true,
            'services.notifications.email.mailer' => 'log',
        ]);
        $user = $this->createUser();
        Sanctum::actingAs($user);

        $this->postJson('/api/v1/accounts/two-factor/setup', [
            'method' => 'email',
            'current_password' => 'password',
        ])->assertOk()
            ->assertJsonPath('body.method', 'email')
            ->assertJsonPath('body.destination', fn (?string $value) => str_contains((string) $value, '@'));

        Notification::assertSentTo($user, TwoFactorCodeNotification::class);
    }

    public function test_delivery_methods_are_available_when_their_settings_and_providers_are_enabled(): void
    {
        $this->installSettings([
            'two_factor_email_enabled' => true,
            'two_factor_sms_enabled' => true,
            'services.notifications.email.enabled' => true,
            'services.notifications.email.mailer' => 'log',
            'services.notifications.sms.enabled' => true,
            'services.notifications.sms.provider' => 'log',
        ]);
        $user = $this->createUser();
        $user->forceFill(['phone' => null])->save();
        Sanctum::actingAs($user->refresh());

        $methods = collect(
            $this->getJson('/api/v1/accounts/two-factor')
                ->assertOk()
                ->json('body.methods'),
        )->keyBy('id');

        $this->assertTrue($methods->get('email')['available']);
        $this->assertTrue($methods->get('sms')['available']);
        $this->assertNull($methods->get('sms')['destination']);
    }

    public function test_sms_setup_reports_a_missing_phone_after_the_method_becomes_available(): void
    {
        Notification::fake();
        $this->installSettings([
            'two_factor_sms_enabled' => true,
            'services.notifications.sms.enabled' => true,
            'services.notifications.sms.provider' => 'log',
        ]);
        $user = $this->createUser();
        $user->forceFill(['phone' => null])->save();
        Sanctum::actingAs($user);

        $this->postJson('/api/v1/accounts/two-factor/setup', [
            'method' => 'sms',
            'current_password' => 'password',
        ])->assertUnprocessable()
            ->assertJsonValidationErrors('method')
            ->assertJsonPath(
                'errors.method.0',
                __('user::auth.two_factor_sms_destination_missing'),
            );

        Notification::assertNothingSent();
    }

    public function test_email_setup_is_unavailable_without_an_enabled_email_service(): void
    {
        $this->installSettings([
            'two_factor_email_enabled' => true,
            'services.notifications.email.enabled' => false,
            'services.notifications.email.mailer' => 'log',
        ]);
        $user = $this->createUser();
        Sanctum::actingAs($user);

        $this->postJson('/api/v1/accounts/two-factor/setup', [
            'method' => 'email',
            'current_password' => 'password',
        ])->assertUnprocessable();
    }

    public function test_sms_setup_is_unavailable_without_an_active_sms_provider(): void
    {
        $this->installSettings([
            'two_factor_sms_enabled' => true,
            'services.notifications.sms.enabled' => false,
        ]);
        $user = $this->createUser();
        Sanctum::actingAs($user);

        $this->postJson('/api/v1/accounts/two-factor/setup', [
            'method' => 'sms',
            'current_password' => 'password',
        ])->assertUnprocessable();
    }

    public function test_email_and_sms_enrollment_and_login_work_end_to_end(): void
    {
        $this->enableDelivery();
        foreach (['email', 'sms'] as $method) {
            $user = $this->createUser();
            $user->forceFill(['phone' => '790000001', 'phone_country_iso_code' => 'JO'])->save();
            Sanctum::actingAs($user);
            $this->postJson('/api/v1/accounts/two-factor/setup', [
                'method' => $method,
                'current_password' => 'password',
            ])->assertOk();
            $this->postJson('/api/v1/accounts/two-factor/confirm', [
                'code' => $this->deliveredCode($user),
            ])->assertOk()->assertJsonCount(8, 'body.recovery_codes');
            $this->travel(61)->seconds();
            $challenge = $this->loginChallenge($user);
            $code = $this->deliveredCode($user);
            $this->postJson('/api/v1/auth/two-factor/verify', [
                'challenge_token' => $challenge['challenge_token'],
                'code' => $code,
            ])->assertOk()->assertJsonStructure(['body' => ['token']]);
            $this->postJson('/api/v1/auth/two-factor/verify', [
                'challenge_token' => $challenge['challenge_token'],
                'code' => $code,
            ])->assertUnprocessable();
        }
    }

    public function test_replacement_requires_an_existing_factor_and_accepts_recovery(): void
    {
        $user = $this->enrolledUser();
        Sanctum::actingAs($user);
        $data = ['method' => 'authenticator', 'current_password' => 'password'];
        $this->postJson('/api/v1/accounts/two-factor/setup', $data)
            ->assertUnprocessable()->assertJsonValidationErrors('code');
        $this->postJson('/api/v1/accounts/two-factor/setup', [...$data, 'code' => 'wrong'])
            ->assertUnprocessable()->assertJsonValidationErrors('code');
        $setup = $this->postJson('/api/v1/accounts/two-factor/setup', [
            ...$data, 'code' => 'ABCDE-12345',
        ])->assertOk()->json('body');
        $this->assertSame([], $user->refresh()->two_factor_recovery_codes);
        $this->postJson('/api/v1/accounts/two-factor/confirm', [
            'code' => $this->totp($setup['secret']),
        ])->assertOk();
    }

    public function test_a_stale_user_snapshot_cannot_replay_a_totp(): void
    {
        $user = $this->enrolledUser();
        $staleUser = $user->fresh();
        $service = app(TwoFactorService::class);
        $verify = new ReflectionMethod($service, 'verifyForUser');
        $code = $this->totp('JBSWY3DPEHPK3PXP');
        $this->assertTrue($verify->invoke($service, $user, $code, 'unused', false));
        $this->assertFalse($verify->invoke($service, $staleUser, $code, 'unused', false));
    }

    public function test_verification_limit_cannot_be_reset_by_identifier_ip_or_new_challenge(): void
    {
        $user = $this->enrolledUser();
        $service = app(TwoFactorService::class);
        for ($attempt = 0; $attempt < 5; $attempt++) {
            $challenge = $service->createLoginChallenge($user);
            $this->withServerVariables(['REMOTE_ADDR' => '192.0.2.'.($attempt + 1)])
                ->postJson('/api/v1/auth/two-factor/verify', [
                    'identifier' => 'arbitrary-'.$attempt,
                    'challenge_token' => $challenge['challenge_token'],
                    'code' => 'wrong',
                ])->assertUnprocessable();
        }
        $challenge = $service->createLoginChallenge($user);
        $this->postJson('/api/v1/auth/two-factor/verify', [
            'identifier' => 'another-bucket',
            'challenge_token' => $challenge['challenge_token'],
            'code' => 'ABCDE-12345',
        ])->assertTooManyRequests()->assertHeader('Retry-After');
        $this->travel(301)->seconds();
        $this->postJson('/api/v1/auth/two-factor/verify', [
            'challenge_token' => $challenge['challenge_token'],
            'code' => 'ABCDE-12345',
        ])->assertOk();
    }

    public function test_setup_confirmation_attempts_are_account_limited(): void
    {
        $user = $this->createUser();
        Sanctum::actingAs($user);
        for ($attempt = 0; $attempt < 5; $attempt++) {
            $this->postJson('/api/v1/accounts/two-factor/setup', [
                'method' => 'authenticator', 'current_password' => 'password',
            ])->assertOk();
            $this->postJson('/api/v1/accounts/two-factor/confirm', ['code' => 'wrong'])
                ->assertUnprocessable();
        }
        $this->postJson('/api/v1/accounts/two-factor/confirm', ['code' => 'wrong'])
            ->assertTooManyRequests();
    }

    public function test_disabled_email_and_sms_services_still_allow_recovery_login(): void
    {
        foreach (['email', 'sms'] as $method) {
            $this->enableDelivery();
            $user = $this->enrolledUser($method);
            $this->enableDelivery(["services.notifications.{$method}.enabled" => false]);
            $challenge = $this->loginChallenge($user);
            $this->assertTrue($challenge['delivery_failed']);
            $this->assertFalse($challenge['can_resend']);
            $this->postJson('/api/v1/auth/two-factor/verify', [
                'challenge_token' => $challenge['challenge_token'], 'code' => 'ABCDE-12345',
            ])->assertOk();
        }
        Notification::assertNothingSent();
    }

    public function test_disabling_an_enrolled_sms_method_still_requires_recovery_login(): void
    {
        $this->enableDelivery();
        $user = $this->enrolledUser('sms');
        $this->enableDelivery(['two_factor_sms_enabled' => false]);

        $challenge = $this->loginChallenge($user);

        $this->assertTrue($challenge['two_factor_required']);
        $this->assertTrue($challenge['delivery_failed']);
        $this->assertFalse($challenge['can_resend']);
        $this->postJson('/api/v1/auth/two-factor/verify', [
            'challenge_token' => $challenge['challenge_token'],
            'code' => 'ABCDE-12345',
        ])->assertOk();
        Notification::assertNothingSent();
    }

    public function test_delivery_exception_does_not_block_recovery_or_leak_provider_details(): void
    {
        $this->enableDelivery();
        $user = $this->enrolledUser('email');
        Notification::shouldReceive('send')->andThrow(new \RuntimeException('private-provider-detail'));
        $response = $this->postJson('/api/v1/auth/login', [
            'identifier' => $user->email, 'password' => 'password',
        ])->assertOk()->assertJsonPath('body.delivery_failed', true);
        $this->assertStringNotContainsString('private-provider-detail', $response->getContent());
        $this->postJson('/api/v1/auth/two-factor/verify', [
            'challenge_token' => $response->json('body.challenge_token'), 'code' => 'ABCDE-12345',
        ])->assertOk();
    }

    public function test_new_login_cannot_bypass_account_delivery_cooldown(): void
    {
        $this->enableDelivery();
        $user = $this->enrolledUser('email');
        $first = $this->loginChallenge($user);
        Sanctum::actingAs($user);
        $this->getJson('/api/v1/accounts/two-factor')
            ->assertOk()
            ->assertJsonPath('body.resend_available_in', 60);
        $second = $this->loginChallenge($user);
        $this->assertFalse($first['delivery_failed']);
        $this->assertTrue($second['delivery_failed']);
        Notification::assertSentToTimes($user, TwoFactorCodeNotification::class, 1);
        $this->travel(61)->seconds();
        $this->postJson('/api/v1/auth/two-factor/resend', [
            'challenge_token' => $second['challenge_token'],
        ])->assertOk()->assertJsonPath('body.delivery_failed', false)
            ->assertJsonPath('body.expires_in', 539);
    }

    public function test_pending_setup_expires_and_is_invalidated_by_password_or_setting_change(): void
    {
        $user = $this->createUser();
        Sanctum::actingAs($user);
        $setup = $this->postJson('/api/v1/accounts/two-factor/setup', [
            'method' => 'authenticator', 'current_password' => 'password',
        ])->assertOk()->json('body');
        $this->installSettings(['two_factor_authenticator_enabled' => false]);
        $this->postJson('/api/v1/accounts/two-factor/confirm', ['code' => $this->totp($setup['secret'])])
            ->assertUnprocessable();
        $this->installSettings();
        $user->forceFill(['password' => 'new-password'])->save();
        $this->postJson('/api/v1/accounts/two-factor/confirm', ['code' => $this->totp($setup['secret'])])
            ->assertUnprocessable();
        $setup = $this->postJson('/api/v1/accounts/two-factor/setup', [
            'method' => 'authenticator', 'current_password' => 'new-password',
        ])->assertOk()->json('body');
        $this->travel(11)->minutes();
        $this->postJson('/api/v1/accounts/two-factor/confirm', ['code' => $this->totp($setup['secret'])])
            ->assertUnprocessable();
    }

    public function test_setup_is_bound_to_the_authenticated_token(): void
    {
        $user = $this->createUser();
        $first = $user->createToken('first');
        $second = $user->createToken('second');
        $user->withAccessToken($first->accessToken);
        $service = app(TwoFactorService::class);
        $setup = $service->beginSetup($user, TwoFactorMethod::Authenticator, 'password');
        $user->withAccessToken($second->accessToken);
        $this->expectException(\Symfony\Component\HttpKernel\Exception\HttpException::class);
        $service->confirmSetup($user, $this->totp($setup['secret']));
    }

    public function test_password_change_invalidates_pending_login(): void
    {
        $user = $this->enrolledUser();
        $challenge = $this->loginChallenge($user);
        $user->forceFill(['password' => 'new-password'])->save();
        $this->postJson('/api/v1/auth/two-factor/verify', [
            'challenge_token' => $challenge['challenge_token'], 'code' => 'ABCDE-12345',
        ])->assertUnprocessable();
        $this->assertCount(1, $user->refresh()->two_factor_recovery_codes);
    }

    public function test_profile_cannot_redirect_an_enrolled_email_factor(): void
    {
        $user = $this->enrolledUser('email');
        $user->assignRole(Role::query()->create([
            'name' => 'super_admin', 'display_name' => 'Admin', 'guard_name' => 'api',
        ]));
        Sanctum::actingAs($user);
        $this->putJson('/api/v1/accounts/profile/update', [
            'name' => $user->name, 'username' => $user->username, 'email' => 'new@example.test',
        ])->assertUnprocessable()->assertJsonValidationErrors('email');
        $this->assertNotSame('new@example.test', $user->refresh()->email);
        $this->putJson('/api/v1/accounts/profile/update', [
            'name' => 'New name', 'username' => $user->username, 'email' => $user->email,
        ])->assertOk();
    }

    public function test_alternate_model_updates_cannot_redirect_enrolled_destinations(): void
    {
        foreach (['email' => 'email', 'sms' => 'phone'] as $method => $field) {
            $user = $this->enrolledUser($method);
            $original = $user->getAttribute($field);

            try {
                $user->forceFill([$field => $method === 'email' ? 'changed@example.test' : '799123456'])->save();
                $this->fail('An enrolled destination was changed without disabling the factor.');
            } catch (ValidationException $exception) {
                $this->assertArrayHasKey($field, $exception->errors());
            }
            $this->assertSame($original, $user->refresh()->getAttribute($field));

            $user->forceFill(['two_factor_method' => null, 'two_factor_confirmed_at' => null])->save();
            $user->forceFill([$field => $method === 'email' ? 'changed@example.test' : '799123456'])->save();
            $this->assertNotSame($original, $user->refresh()->getAttribute($field));
        }
    }

    public function test_sms_country_change_requires_factor_reenrollment(): void
    {
        $user = $this->enrolledUser('sms');
        $this->expectException(ValidationException::class);
        $user->forceFill(['phone_country_iso_code' => 'GB'])->save();
    }

    public function test_setup_delivery_failure_returns_safe_validation_error(): void
    {
        $this->enableDelivery();
        $user = $this->createUser();
        Sanctum::actingAs($user);
        Notification::shouldReceive('send')->andThrow(new \RuntimeException('private-provider-detail'));

        $response = $this->postJson('/api/v1/accounts/two-factor/setup', [
            'method' => 'email',
            'current_password' => 'password',
        ])->assertUnprocessable()->assertJsonValidationErrors('method');

        $this->assertStringNotContainsString('private-provider-detail', $response->getContent());
        $this->assertNull($user->refresh()->two_factor_confirmed_at);
    }

    public function test_setup_resend_requires_authentication_and_pending_setup(): void
    {
        $this->postJson('/api/v1/accounts/two-factor/setup/resend')->assertUnauthorized();
        Sanctum::actingAs($this->createUser());
        $this->postJson('/api/v1/accounts/two-factor/setup/resend')->assertUnprocessable();
        Notification::assertNothingSent();
    }

    public function test_email_and_sms_setup_resends_replace_the_code_and_enforce_cooldown(): void
    {
        $this->enableDelivery();
        foreach (['email', 'sms'] as $method) {
            $user = $this->createUser();
            $user->forceFill(['phone' => '790000001', 'phone_country_iso_code' => 'JO'])->save();
            Sanctum::actingAs($user);
            $token = $user->createToken('Resend fixture')->accessToken;
            $user->withAccessToken($token);
            $this->postJson('/api/v1/accounts/two-factor/setup', [
                'method' => $method, 'current_password' => 'password',
            ])->assertOk()->assertJsonPath('body.resend_available_in', 60);
            $this->postJson('/api/v1/accounts/two-factor/setup/resend')
                ->assertTooManyRequests()->assertHeader('Retry-After', '60');
            Notification::assertSentToTimes($user, TwoFactorCodeNotification::class, 1);

            $this->travel(61)->seconds();
            $cache = Cache::store('two_factor_testing');
            $key = 'two-factor:setup:'.$user->id.':'.$token->getKey();
            $before = $cache->get($key);
            // A deterministic old value proves replacement without random OTP collisions.
            $before['code_hash'] = bcrypt('previous-code');
            $cache->put($key, $before, now()->setTimestamp($before['expires_at']));
            $this->postJson('/api/v1/accounts/two-factor/setup/resend')
                ->assertOk()->assertJsonPath('body.method', $method)
                ->assertJsonPath('body.resend_available_in', 60)
                ->assertJsonPath('body.expires_in', 539);
            $this->assertSame($before['expires_at'], $cache->get($key)['expires_at']);
            Notification::assertSentToTimes($user, TwoFactorCodeNotification::class, 2);
            $this->postJson('/api/v1/accounts/two-factor/confirm', ['code' => 'previous-code'])->assertUnprocessable();
            $this->postJson('/api/v1/accounts/two-factor/confirm', ['code' => $this->deliveredCode($user)])
                ->assertOk()->assertJsonPath('body.method', $method);
            $this->postJson('/api/v1/accounts/two-factor/setup/resend')->assertUnprocessable();
        }
    }

    public function test_setup_resend_is_session_bound_and_rejects_changed_credentials(): void
    {
        $this->enableDelivery();
        $user = $this->createUser();
        Sanctum::actingAs($user);
        $originalToken = $user->createToken('Setup session')->accessToken;
        $user->withAccessToken($originalToken);
        $this->postJson('/api/v1/accounts/two-factor/setup', [
            'method' => 'email', 'current_password' => 'password',
        ])->assertOk();
        $this->travel(61)->seconds();
        $user->withAccessToken($user->createToken('Other session')->accessToken);
        $this->postJson('/api/v1/accounts/two-factor/setup/resend')->assertUnprocessable();
        $user->withAccessToken($originalToken);
        $user->fresh()->forceFill(['password' => 'new-password'])->save();
        $this->postJson('/api/v1/accounts/two-factor/setup/resend')->assertUnprocessable();
        Notification::assertSentToTimes($user, TwoFactorCodeNotification::class, 1);
    }

    public function test_setup_resend_does_not_extend_approval_or_reset_verification_attempts(): void
    {
        $this->enableDelivery();
        $user = $this->createUser();
        Sanctum::actingAs($user);
        $this->postJson('/api/v1/accounts/two-factor/setup', [
            'method' => 'email', 'current_password' => 'password',
        ])->assertOk();
        for ($attempt = 0; $attempt < 5; $attempt++) {
            $this->postJson('/api/v1/accounts/two-factor/confirm', ['code' => 'invalid'])->assertUnprocessable();
        }
        $this->travel(61)->seconds();
        $this->postJson('/api/v1/accounts/two-factor/setup/resend')->assertOk();
        $this->postJson('/api/v1/accounts/two-factor/confirm', ['code' => $this->deliveredCode($user)])
            ->assertTooManyRequests();
        $this->travel(540)->seconds();
        $this->postJson('/api/v1/accounts/two-factor/setup/resend')->assertUnprocessable();
        Notification::assertSentToTimes($user, TwoFactorCodeNotification::class, 2);
    }

    public function test_setup_resend_rejects_authenticator_and_disabled_delivery(): void
    {
        $this->enableDelivery();
        $user = $this->createUser();
        Sanctum::actingAs($user);
        $this->postJson('/api/v1/accounts/two-factor/setup', [
            'method' => 'authenticator', 'current_password' => 'password',
        ])->assertOk()->assertJsonPath('body.resend_available_in', 0);
        $this->postJson('/api/v1/accounts/two-factor/setup/resend')->assertUnprocessable();
        $this->postJson('/api/v1/accounts/two-factor/setup', [
            'method' => 'email', 'current_password' => 'password',
        ])->assertOk();
        $this->travel(61)->seconds();
        $this->enableDelivery(['services.notifications.email.enabled' => false]);
        $this->postJson('/api/v1/accounts/two-factor/setup/resend')->assertUnprocessable();
        Notification::assertSentToTimes($user, TwoFactorCodeNotification::class, 1);
    }

    public function test_failed_setup_resend_preserves_the_previous_code_and_reserves_cooldown(): void
    {
        $this->enableDelivery();
        $user = $this->createUser();
        Sanctum::actingAs($user);
        $this->postJson('/api/v1/accounts/two-factor/setup', [
            'method' => 'email', 'current_password' => 'password',
        ])->assertOk();
        $code = $this->deliveredCode($user);
        $this->travel(61)->seconds();
        Notification::shouldReceive('send')->andThrow(new \RuntimeException('private-provider-detail'));
        $response = $this->postJson('/api/v1/accounts/two-factor/setup/resend')
            ->assertUnprocessable()->assertJsonValidationErrors('code');
        $this->assertStringNotContainsString('private-provider-detail', $response->getContent());
        $this->postJson('/api/v1/accounts/two-factor/setup/resend')->assertTooManyRequests();
        Notification::fake();
        $this->postJson('/api/v1/accounts/two-factor/confirm', ['code' => $code])->assertOk();
    }

    private function enableDelivery(array $overrides = []): void
    {
        $this->installSettings([
            'two_factor_email_enabled' => true,
            'two_factor_sms_enabled' => true,
            'services.notifications.email.enabled' => true,
            'services.notifications.email.mailer' => 'log',
            'services.notifications.sms.enabled' => true,
            'services.notifications.sms.provider' => 'log',
            ...$overrides,
        ]);
    }

    private function enrolledUser(string $method = 'authenticator'): User
    {
        $user = $this->createUser();
        $user->forceFill([
            'two_factor_method' => $method,
            'two_factor_secret' => 'JBSWY3DPEHPK3PXP',
            'two_factor_recovery_codes' => [bcrypt('ABCDE-12345')],
            'two_factor_confirmed_at' => now(),
        ])->save();

        return $user;
    }

    private function loginChallenge(User $user): array
    {
        return $this->postJson('/api/v1/auth/login', [
            'identifier' => $user->email, 'password' => 'password',
        ])->assertOk()->assertJsonMissingPath('body.token')->json('body');
    }

    private function deliveredCode(User $user): string
    {
        $notification = Notification::sent($user, TwoFactorCodeNotification::class)->last();

        return (new ReflectionProperty($notification, 'code'))->getValue($notification);
    }

    private function createUser(): User
    {
        $user = User::factory()->create([
            'password' => 'password',
            'is_active' => true,
        ]);
        $role = Role::query()->firstOrCreate([
            'name' => 'two_factor_test',
            'guard_name' => 'api',
        ], [
            'display_name' => 'Two Factor Test',
        ]);
        $user->assignRole($role);

        return $user;
    }

    private function installSettings(array $overrides = []): void
    {
        $settings = [
            'app_name' => 'Forkiva',
            'two_factor_enabled' => true,
            'two_factor_authenticator_enabled' => true,
            'two_factor_email_enabled' => false,
            'two_factor_sms_enabled' => false,
            ...$overrides,
        ];

        app()->instance('setting', new SettingRepository(
            collect($settings)->map(fn (mixed $value): array => [
                'payload' => serialize($value),
                'is_encryptable' => false,
                'is_translatable' => false,
            ])
        ));
    }

    private function totp(string $secret): string
    {
        $alphabet = array_flip(str_split('ABCDEFGHIJKLMNOPQRSTUVWXYZ234567'));
        $bits = '';
        foreach (str_split(strtoupper($secret)) as $character) {
            $bits .= str_pad(decbin($alphabet[$character]), 5, '0', STR_PAD_LEFT);
        }

        $decoded = '';
        foreach (str_split($bits, 8) as $chunk) {
            if (strlen($chunk) === 8) {
                $decoded .= chr(bindec($chunk));
            }
        }

        $timestep = (int) floor(now()->getTimestamp() / 30);
        $hash = hash_hmac('sha1', pack('N2', 0, $timestep), $decoded, true);
        $offset = ord($hash[19]) & 0x0F;
        $binary = ((ord($hash[$offset]) & 0x7F) << 24)
            | ((ord($hash[$offset + 1]) & 0xFF) << 16)
            | ((ord($hash[$offset + 2]) & 0xFF) << 8)
            | (ord($hash[$offset + 3]) & 0xFF);

        return str_pad((string) ($binary % 1_000_000), 6, '0', STR_PAD_LEFT);
    }
}
