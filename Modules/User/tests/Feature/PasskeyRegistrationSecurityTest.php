<?php

namespace Modules\User\Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Notification;
use Illuminate\Validation\ValidationException;
use Laravel\Passkeys\Actions\StorePasskey;
use Laravel\Passkeys\Support\WebAuthn;
use Laravel\Sanctum\Sanctum;
use Modules\Setting\Repositories\SettingRepository;
use Modules\User\Models\User;
use Modules\User\Providers\DeferredUserServiceProvider;
use Modules\User\Services\Auth\PasskeyRegistrationService;
use Modules\User\Services\Auth\PasskeyRegistrationServiceInterface;
use ParagonIE\ConstantTime\Base64UrlSafe;
use Tests\TestCase;
use Webauthn\AuthenticatorAssertionResponse;
use Webauthn\AuthenticatorData;
use Webauthn\CollectedClientData;
use Webauthn\PublicKeyCredential;

class PasskeyRegistrationSecurityTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config([
            'passkeys.cache_store' => 'array',
            'user.two_factor.cache_store' => 'array',
        ]);
        Notification::fake();
        $this->settings();
    }

    public function test_registration_interface_is_provided_as_a_deferred_singleton(): void
    {
        $provider = new DeferredUserServiceProvider($this->app);
        $this->assertTrue($provider->isDeferred());
        $this->assertContains(PasskeyRegistrationServiceInterface::class, $provider->provides());

        $service = app(PasskeyRegistrationServiceInterface::class);
        $this->assertInstanceOf(PasskeyRegistrationService::class, $service);
        $this->assertSame($service, app(PasskeyRegistrationServiceInterface::class));
    }

    public function test_options_require_password_and_reject_the_old_get_endpoint(): void
    {
        $user = $this->user();
        $this->signIn($user);
        $this->getJson('/api/v1/accounts/passkeys/options')->assertStatus(405);
        $this->postJson('/api/v1/accounts/passkeys/options')
            ->assertUnprocessable()->assertJsonValidationErrors('current_password');
        $this->postJson('/api/v1/accounts/passkeys/options', ['current_password' => 'wrong'])
            ->assertUnprocessable()->assertJsonValidationErrors('current_password');
        $this->postJson('/api/v1/accounts/passkeys/options', ['current_password' => 'password'])
            ->assertOk()->assertJsonStructure(['body' => ['options' => ['challenge']]]);
    }

    public function test_enrolled_accounts_require_current_factor_even_when_global_enforcement_is_off(): void
    {
        $user = $this->user(enrolled: true);
        $this->signIn($user);
        $this->settings(['two_factor_enabled' => false]);
        $this->postJson('/api/v1/accounts/passkeys/options', ['current_password' => 'password'])
            ->assertUnprocessable()->assertJsonValidationErrors('code');
        $this->postJson('/api/v1/accounts/passkeys/options', [
            'current_password' => 'password',
            'code' => 'ABCDE-12345',
        ])->assertOk();
        $this->assertSame([], $user->refresh()->two_factor_recovery_codes);
    }

    public function test_wrong_password_attempts_are_rate_limited_for_the_account(): void
    {
        $user = $this->user();
        $this->signIn($user);
        for ($attempt = 0; $attempt < 5; $attempt++) {
            $this->postJson('/api/v1/accounts/passkeys/options', ['current_password' => 'wrong'])
                ->assertUnprocessable();
        }
        $this->postJson('/api/v1/accounts/passkeys/options', ['current_password' => 'password'])
            ->assertTooManyRequests();
    }

    public function test_wrong_factor_attempts_are_rate_limited_for_the_account(): void
    {
        $user = $this->user(enrolled: true);
        $this->signIn($user);
        for ($attempt = 0; $attempt < 5; $attempt++) {
            $this->postJson('/api/v1/accounts/passkeys/options', [
                'current_password' => 'password',
                'code' => 'WRONG-00000',
            ])->assertUnprocessable();
        }
        $this->postJson('/api/v1/accounts/passkeys/options', [
            'current_password' => 'password',
            'code' => 'ABCDE-12345',
        ])->assertTooManyRequests();
        $this->assertCount(1, $user->refresh()->two_factor_recovery_codes);
    }

    public function test_approval_is_consumed_once_and_allows_the_authorized_registration(): void
    {
        $user = $this->user(enrolled: true);
        $this->signIn($user);
        $store = $this->mock(StorePasskey::class);
        $store->shouldReceive('__invoke')->once()->andReturnUsing(
            fn (User $user, string $name) => $user->passkeys()->create([
                'name' => $name,
                'credential_id' => 'Zml4dHVyZQ',
                'credential' => [],
            ]),
        );
        $service = app(PasskeyRegistrationServiceInterface::class);
        $options = $service->begin($user, 'password', 'ABCDE-12345');
        $credential = $this->credential($options->challenge);
        $this->assertSame('Fixture passkey', $service->complete($user, 'Fixture passkey', $credential)->name);

        $this->expectException(ValidationException::class);
        $service->complete($user, 'Replay', $credential);
    }

    public function test_completion_without_a_prior_approval_is_rejected_before_webauthn_storage(): void
    {
        $user = $this->user();
        $this->signIn($user);
        $this->mock(StorePasskey::class)->shouldNotReceive('__invoke');
        $this->expectException(ValidationException::class);
        app(PasskeyRegistrationServiceInterface::class)->complete($user, 'Bypass', $this->credential(random_bytes(32)));
    }

    public function test_approval_cannot_cross_authenticated_tokens(): void
    {
        $user = $this->user();
        $this->signIn($user);
        $this->mock(StorePasskey::class)->shouldNotReceive('__invoke');
        $service = app(PasskeyRegistrationServiceInterface::class);
        $options = $service->begin($user, 'password');
        $user->withAccessToken($user->createToken('Other session')->accessToken);
        $this->expectException(ValidationException::class);
        $service->complete($user, 'Wrong session', $this->credential($options->challenge));
    }

    public function test_password_change_invalidates_the_approval_even_with_a_stale_user_instance(): void
    {
        $user = $this->user();
        $this->signIn($user);
        $this->mock(StorePasskey::class)->shouldNotReceive('__invoke');
        $service = app(PasskeyRegistrationServiceInterface::class);
        $options = $service->begin($user, 'password');
        $user->fresh()->forceFill(['password' => 'replacement-password'])->save();
        $this->expectException(ValidationException::class);
        $service->complete($user, 'Stale approval', $this->credential($options->challenge));
    }

    public function test_factor_change_invalidates_the_approval(): void
    {
        $user = $this->user();
        $this->signIn($user);
        $this->mock(StorePasskey::class)->shouldNotReceive('__invoke');
        $service = app(PasskeyRegistrationServiceInterface::class);
        $options = $service->begin($user, 'password');
        $user->fresh()->forceFill([
            'two_factor_method' => 'authenticator',
            'two_factor_secret' => 'JBSWY3DPEHPK3PXP',
            'two_factor_confirmed_at' => now(),
        ])->save();
        $this->expectException(ValidationException::class);
        $service->complete($user, 'Stale factor', $this->credential($options->challenge));
    }

    public function test_registration_approval_expires_within_five_minutes(): void
    {
        config(['passkeys.timeout' => 900_000]);
        $user = $this->user();
        $this->signIn($user);
        $this->mock(StorePasskey::class)->shouldNotReceive('__invoke');
        $service = app(PasskeyRegistrationServiceInterface::class);
        $options = $service->begin($user, 'password');
        $this->travel(301)->seconds();
        $this->expectException(ValidationException::class);
        $service->complete($user, 'Expired', $this->credential($options->challenge));
    }

    public function test_legacy_unbound_registration_options_are_rejected(): void
    {
        $user = $this->user();
        $this->signIn($user);
        $this->mock(StorePasskey::class)->shouldNotReceive('__invoke');
        $service = app(PasskeyRegistrationServiceInterface::class);
        $options = $service->begin($user, 'password');
        Cache::store('array')->put(
            'passkeys:registration:'.$user->id.':'.hash('sha256', $options->challenge),
            WebAuthn::toJson($options),
            300,
        );

        $this->expectException(ValidationException::class);
        $service->complete($user, 'Legacy approval', $this->credential($options->challenge));
    }

    public function test_recovery_code_rotation_invalidates_the_approval(): void
    {
        $user = $this->user(enrolled: true);
        $this->signIn($user);
        $this->mock(StorePasskey::class)->shouldNotReceive('__invoke');
        $service = app(PasskeyRegistrationServiceInterface::class);
        $options = $service->begin($user, 'password', 'ABCDE-12345');
        $user->fresh()->forceFill(['two_factor_recovery_codes' => [bcrypt('NEWCD-12345')]])->save();

        $this->expectException(ValidationException::class);
        $service->complete($user, 'Rotated recovery codes', $this->credential($options->challenge));
    }

    private function user(bool $enrolled = false): User
    {
        $user = User::factory()->create(['is_active' => true, 'password' => 'password']);
        if ($enrolled) {
            $user->forceFill([
                'two_factor_method' => 'authenticator',
                'two_factor_secret' => 'JBSWY3DPEHPK3PXP',
                'two_factor_recovery_codes' => [bcrypt('ABCDE-12345')],
                'two_factor_confirmed_at' => now(),
            ])->save();
        }

        return $user;
    }

    private function signIn(User $user): void
    {
        Sanctum::actingAs($user);
        $user->withAccessToken($user->createToken('Security fixture')->accessToken);
    }

    private function settings(array $overrides = []): void
    {
        $values = [
            'passkeys_enabled' => true,
            'two_factor_enabled' => true,
            'two_factor_authenticator_enabled' => true,
            ...$overrides,
        ];
        app()->instance('setting', new SettingRepository(collect($values)->map(fn ($value) => [
            'payload' => serialize($value),
            'is_encryptable' => false,
            'is_translatable' => false,
        ])));
    }

    /**
     * Only the challenge is needed here; the WebAuthn storage action is mocked explicitly.
     */
    private function credential(string $challenge): PublicKeyCredential
    {
        $client = CollectedClientData::create('', [
            'type' => 'webauthn.get',
            'challenge' => Base64UrlSafe::encodeUnpadded($challenge),
            'origin' => config('passkeys.allowed_origins')[0],
        ]);

        return PublicKeyCredential::create('public-key', 'fixture-id', AuthenticatorAssertionResponse::create(
            $client,
            AuthenticatorData::create('', '', "\x05", 0),
            'fixture-signature',
        ));
    }
}
