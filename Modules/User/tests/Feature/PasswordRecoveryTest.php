<?php

namespace Modules\User\Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Queue;
use Modules\User\Events\PasswordChanged;
use Modules\User\Jobs\SendPasswordRecoveryCode;
use Modules\User\Models\User;
use Modules\User\Notifications\PasswordRecoveryCodeNotification;
use Modules\User\Providers\DeferredUserServiceProvider;
use Modules\User\Services\PasswordRecovery\PasswordRecoveryIdentity;
use Modules\User\Services\PasswordRecovery\PasswordRecoveryIdentityInterface;
use Modules\User\Services\PasswordRecovery\PasswordRecoveryService;
use Modules\User\Services\PasswordRecovery\PasswordRecoveryServiceInterface;
use ReflectionProperty;
use Tests\TestCase;

class PasswordRecoveryTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->freezeSecond();
        config(['user.password_recovery.cache_store' => 'array']);
        Notification::fake();
        Event::fake([PasswordChanged::class]);
        $this->settings();
    }

    public function test_recovery_interface_is_provided_as_a_deferred_singleton(): void
    {
        $provider = new DeferredUserServiceProvider($this->app);
        $this->assertTrue($provider->isDeferred());
        $this->assertContains(PasswordRecoveryServiceInterface::class, $provider->provides());

        $service = app(PasswordRecoveryServiceInterface::class);
        $this->assertInstanceOf(PasswordRecoveryService::class, $service);
        $this->assertSame($service, app(PasswordRecoveryServiceInterface::class));
    }

    public function test_identity_interface_is_provided_as_a_deferred_singleton(): void
    {
        $provider = new DeferredUserServiceProvider($this->app);
        $this->assertTrue($provider->isDeferred());
        $this->assertContains(PasswordRecoveryIdentityInterface::class, $provider->provides());

        $identity = app(PasswordRecoveryIdentityInterface::class);
        $this->assertInstanceOf(PasswordRecoveryIdentity::class, $identity);
        $this->assertSame($identity, app(PasswordRecoveryIdentityInterface::class));
    }

    public function test_missing_recovery_preferences_default_to_enabled_in_settings_and_public_options(): void
    {
        foreach (['password_recovery_enabled', 'password_recovery_email_enabled', 'password_recovery_sms_enabled'] as $key) {
            unset(app('setting')[$key]);
        }
        $settings = app(\Modules\Setting\Services\Setting\SettingServiceInterface::class)
            ->getSettings(\Modules\Setting\Enums\SettingSection::PasswordRecovery);
        $this->assertTrue($settings['password_recovery_enabled']);
        $this->assertTrue($settings['password_recovery_email_enabled']);
        $this->assertTrue($settings['password_recovery_sms_enabled']);
        $this->getJson('/api/v1/auth/password-recovery/options')->assertOk()->assertJsonPath('body.channels', ['email', 'sms']);
    }

    public function test_saved_disabled_preferences_override_the_enabled_defaults(): void
    {
        $this->settings(['password_recovery_enabled' => false]);
        $settings = app(\Modules\Setting\Services\Setting\SettingServiceInterface::class)
            ->getSettings(\Modules\Setting\Enums\SettingSection::PasswordRecovery);
        $this->assertFalse($settings['password_recovery_enabled']);
        $this->getJson('/api/v1/auth/password-recovery/options')->assertOk()->assertJsonPath('body.channels', []);
        $this->settings(['password_recovery_email_enabled' => false, 'password_recovery_sms_enabled' => true]);
        $this->getJson('/api/v1/auth/password-recovery/options')->assertOk()->assertJsonPath('body.channels', ['sms']);
    }

    public function test_only_recovery_and_service_switches_control_public_channels(): void
    {
        $this->getJson('/api/v1/auth/password-recovery/options')->assertOk()->assertJsonPath('body.channels', ['email']);
        $this->settings([
            'password_recovery_sms_enabled' => true,
            'services.notifications.email.mailer' => 'missing-driver',
            'services.notifications.email.smtp.host' => '',
            'services.notifications.sms.provider' => 'missing-driver',
            'services.notifications.sms.twilio.token' => '',
            'services.notifications.sms.sender_name' => '',
        ]);
        $this->getJson('/api/v1/auth/password-recovery/options')->assertOk()->assertJsonPath('body.channels', ['email', 'sms']);
        $settings = app(\Modules\Setting\Services\Setting\SettingServiceInterface::class)
            ->getSettings(\Modules\Setting\Enums\SettingSection::PasswordRecovery);
        $this->assertSame(['email' => true, 'sms' => true], $settings['providers']);

        $identity = $this->identify('nobody@example.com');
        $this->settings(['password_recovery_enabled' => false]);
        $this->getJson('/api/v1/auth/password-recovery/options')->assertOk()->assertJsonPath('body.channels', []);
        $this->postJson('/api/v1/auth/password-recovery/code', ['channel' => 'email', 'recovery_token' => $identity['recovery_token']])->assertUnprocessable();

        foreach ([['false', 'true', ['sms']], ['true', 'false', ['email']], [false, false, []]] as [$email, $sms, $channels]) {
            $this->settings([
                'password_recovery_sms_enabled' => true,
                'services.notifications.email.enabled' => $email,
                'services.notifications.sms.enabled' => $sms,
            ]);
            $this->getJson('/api/v1/auth/password-recovery/options')->assertOk()->assertJsonPath('body.channels', $channels);
            $settings = app(\Modules\Setting\Services\Setting\SettingServiceInterface::class)
                ->getSettings(\Modules\Setting\Enums\SettingSection::PasswordRecovery);
            $this->assertSame(in_array('email', $channels, true), $settings['providers']['email']);
            $this->assertSame(in_array('sms', $channels, true), $settings['providers']['sms']);
            $disabledChannel = in_array('email', $channels, true) ? 'sms' : 'email';
            $this->postJson('/api/v1/auth/password-recovery/code', [
                'channel' => $disabledChannel,
                'recovery_token' => $identity['recovery_token'],
            ])->assertUnprocessable();
        }
    }

    public function test_log_and_array_channels_are_available_without_external_credentials(): void
    {
        foreach (['log', 'array'] as $driver) {
            $this->settings([
                'password_recovery_sms_enabled' => true,
                'services.notifications.email.mailer' => $driver,
                'services.notifications.email.smtp.host' => '',
                'services.notifications.sms.provider' => $driver,
                'services.notifications.sms.twilio.token' => '',
                'services.notifications.sms.sender_name' => '',
            ]);
            $this->getJson('/api/v1/auth/password-recovery/options')->assertOk()->assertJsonPath('body.channels', ['email', 'sms']);
            $user = User::factory()->create(['is_active' => true]);
            $challenge = $this->begin($user->email);
            $notification = Notification::sent($user, PasswordRecoveryCodeNotification::class)->last();
            $this->assertNull($notification->toMail($user)->mailer);
            $this->resetCode($challenge, $this->code($user))->assertOk();
        }
        $this->settings([
            'password_recovery_sms_enabled' => true,
            'services.notifications.email.mailer' => 'array',
            'services.notifications.sms.provider' => 'log',
            'services.notifications.email.enabled' => false,
            'services.notifications.sms.enabled' => false,
        ]);
        $this->getJson('/api/v1/auth/password-recovery/options')->assertOk()->assertJsonPath('body.channels', []);
    }

    public function test_recovery_job_delivers_email_using_the_application_array_mailer(): void
    {
        config(['mail.default' => 'array']);
        $this->settings(['services.notifications.email.mailer' => 'array']);
        Notification::swap(new \Illuminate\Notifications\ChannelManager(app()));
        $user = User::factory()->create(['is_active' => true]);
        $challenge = $this->begin($user->email);
        $messages = app('mail.manager')->mailer('array')->getSymfonyTransport()->messages();
        $this->assertCount(1, $messages);
        $message = $messages->first()->getOriginalMessage();
        $this->assertSame($user->email, $message->getTo()[0]->getAddress());
        preg_match('/\b([1-9][0-9]{5})\b/', $message->getTextBody(), $matches);
        $this->assertArrayHasKey(1, $matches);
        $this->resetCode($challenge, $matches[1])->assertOk();
    }

    public function test_recovery_job_delivers_email_using_the_application_log_mailer(): void
    {
        config([
            'mail.default' => 'log',
            'mail.mailers.log.channel' => 'recovery_test',
            'logging.channels.recovery_test' => [
                'driver' => 'monolog',
                'handler' => \Monolog\Handler\TestHandler::class,
            ],
        ]);
        $this->settings(['services.notifications.email.mailer' => 'log']);
        Notification::swap(new \Illuminate\Notifications\ChannelManager(app()));
        $user = User::factory()->create(['is_active' => true]);
        $challenge = $this->begin($user->email);
        $handler = app('log')->channel('recovery_test')->getLogger()->getHandlers()[0];
        $records = $handler->getRecords();
        $this->assertCount(1, $records);
        $this->assertStringContainsString($user->email, $records[0]['message']);
        $service = app(PasswordRecoveryServiceInterface::class);
        $this->assertNotNull($service->cache()->get($service->key($challenge['challenge_token'])));
    }

    public function test_recovery_notification_delivers_sms_using_the_array_driver(): void
    {
        $this->settings(['password_recovery_sms_enabled' => true, 'services.notifications.sms.provider' => 'array']);
        $user = User::factory()->create(['is_active' => true, 'phone' => '+962791234567', 'phone_country_iso_code' => 'JO']);
        $challenge = $this->begin($user->username, 'sms');
        $notification = Notification::sent($user, PasswordRecoveryCodeNotification::class)->last();
        $response = app(\Prgayman\Sms\Channels\SmsChannel::class)->send($user, $notification);
        $this->assertTrue($response->successful());
        $this->assertSame('+962791234567', str_replace(' ', '', $response->getRequest()['to']));
        $this->assertStringContainsString($this->code($user), $response->getRequest()['message']);
        $this->resetCode($challenge, $this->code($user))->assertOk();
    }

    public function test_email_code_resets_once_revokes_tokens_and_preserves_second_factor(): void
    {
        $user = User::factory()->create(['is_active' => true, 'two_factor_method' => 'authenticator', 'two_factor_confirmed_at' => now()]);
        $user->createToken('browser');
        $user->createToken('mobile');
        $challenge = $this->begin($user->email);
        $code = $this->code($user);
        $this->resetCode($challenge, $code)->assertOk()->assertJsonPath('body.success', true)->assertJsonMissingPath('body.token');
        $this->assertTrue(Hash::check('NewPassword123!', $user->refresh()->password));
        $this->assertSame(0, $user->tokens()->count());
        $this->assertNotNull($user->two_factor_confirmed_at);
        Event::assertDispatched(PasswordChanged::class);
        $this->resetCode($challenge, $code)->assertUnprocessable();
    }

    public function test_unknown_inactive_and_known_accounts_have_the_same_response(): void
    {
        $active = User::factory()->create(['is_active' => true]);
        $inactive = User::factory()->create(['is_active' => false]);
        $known = $this->begin($active->email);
        $unknown = $this->begin('unknown@example.com');
        $disabled = $this->begin($inactive->email);
        foreach ([$unknown, $disabled] as $challenge) {
            $this->assertSame(array_keys($known), array_keys($challenge));
            $this->assertSame($known['expires_in'], $challenge['expires_in']);
            $this->resetCode($challenge, '123456')->assertUnprocessable();
        }
        Notification::assertNotSentTo($inactive, PasswordRecoveryCodeNotification::class);
        Notification::assertCount(1);
    }

    public function test_sms_uses_the_stored_international_number(): void
    {
        $this->settings(['password_recovery_email_enabled' => false, 'password_recovery_sms_enabled' => true]);
        $user = User::factory()->create(['is_active' => true, 'phone' => '+962791234567', 'phone_country_iso_code' => 'JO']);
        $challenge = $this->begin($user->username, 'sms');
        Notification::assertSentTo($user, PasswordRecoveryCodeNotification::class, fn ($notification, $channels) => $channels === [\Prgayman\Sms\Channels\SmsChannel::class]);
        $this->resetCode($challenge, $this->code($user))->assertOk();
    }

    public function test_a_code_expires_after_ten_minutes(): void
    {
        $user = User::factory()->create(['is_active' => true]);
        $challenge = $this->begin($user->email);
        $code = $this->code($user);
        $this->travel(601)->seconds();
        $this->resetCode($challenge, $code)->assertUnprocessable();
    }

    public function test_five_wrong_guesses_exhaust_the_challenge(): void
    {
        $user = User::factory()->create(['is_active' => true]);
        $challenge = $this->begin($user->email);
        for ($i = 0; $i < 5; $i++) {
            $this->resetCode($challenge, '000000')->assertUnprocessable();
        }
        $this->resetCode($challenge, $this->code($user))->assertUnprocessable();
    }

    public function test_resend_replaces_the_previous_code_and_enforces_cooldown(): void
    {
        $user = User::factory()->create(['is_active' => true]);
        $old = $this->begin($user->email);
        $oldCode = $this->code($user);
        $this->postJson('/api/v1/auth/password-recovery/code', ['channel' => 'email', 'recovery_token' => $this->identify($user->email)['recovery_token']])->assertStatus(429);
        $this->travel(61)->seconds();
        $new = $this->begin(strtoupper($user->email));
        $this->resetCode($old, $oldCode)->assertUnprocessable();
        $this->resetCode($new, $this->code($user))->assertOk();
    }

    public function test_provider_disable_and_account_changes_invalidate_pending_codes(): void
    {
        $user = User::factory()->create(['is_active' => true]);
        $challenge = $this->begin($user->email);
        $code = $this->code($user);
        $this->settings(['services.notifications.email.enabled' => false]);
        $this->resetCode($challenge, $code)->assertUnprocessable();
        $this->settings();
        $user->forceFill(['email' => 'changed@example.com'])->save();
        $this->resetCode($challenge, $code)->assertUnprocessable();
    }

    public function test_password_validation_and_ip_throttling(): void
    {
        $user = User::factory()->create(['is_active' => true]);
        $identity = $this->identify($user->email);
        $challenge = $this->sendIdentified($identity, 'email');
        $this->postJson('/api/v1/auth/password-recovery/reset', [
            'challenge_token' => $challenge['challenge_token'], 'code' => $this->code($user), 'password' => 'weak', 'password_confirmation' => 'weak',
        ])->assertUnprocessable()->assertJsonValidationErrors('password');
        for ($i = 0; $i < 4; $i++) {
            $this->begin("unknown$i@example.com");
        }
        $this->postJson('/api/v1/auth/password-recovery/code', ['channel' => 'email', 'recovery_token' => $identity['recovery_token']])->assertStatus(429);
    }

    public function test_hourly_destination_limit_survives_resends(): void
    {
        for ($i = 0; $i < 5; $i++) {
            $this->begin('unknown@example.com');
            $this->travel(61)->seconds();
        }
        $this->postJson('/api/v1/auth/password-recovery/code', ['channel' => 'email', 'recovery_token' => $this->identify('UNKNOWN@example.com')['recovery_token']])->assertStatus(429);
    }

    public function test_queue_payload_is_encrypted_and_cache_contains_only_a_code_hash(): void
    {
        Queue::fake();
        $user = User::factory()->create(['is_active' => true]);
        $challenge = $this->begin($user->email);
        Queue::assertPushed(SendPasswordRecoveryCode::class, fn ($job) => $job instanceof \Illuminate\Contracts\Queue\ShouldBeEncrypted);
        $service = app(PasswordRecoveryServiceInterface::class);
        $payload = $service->cache()->get($service->key($challenge['challenge_token']));
        $this->assertArrayNotHasKey('code', $payload);
        $this->assertStringStartsWith('$2y$', $payload['code_hash']);
    }

    public function test_queued_delivery_rechecks_settings_and_account_state(): void
    {
        Queue::fake();
        $user = User::factory()->create(['is_active' => true]);
        $this->begin($user->email);
        $job = Queue::pushed(SendPasswordRecoveryCode::class)->first();
        $this->settings(['services.notifications.email.enabled' => false]);
        app()->call([$job, 'handle']);
        Notification::assertNothingSent();
        $this->settings();
        $user->forceFill(['is_active' => false])->save();
        app()->call([$job, 'handle']);
        Notification::assertNothingSent();
    }

    public function test_delivery_failure_is_generic_and_never_leaves_a_usable_code(): void
    {
        Notification::swap(\Mockery::mock(\Illuminate\Contracts\Notifications\Dispatcher::class)
            ->shouldReceive('send')->andThrow(new \RuntimeException('Provider failure'))->getMock());
        $user = User::factory()->create(['is_active' => true]);
        $challenge = $this->begin($user->email);
        $service = app(PasswordRecoveryServiceInterface::class);
        $this->assertNull($service->cache()->get($service->key($challenge['challenge_token'])));
    }

    public function test_identifier_lookup_accepts_username_and_email_without_returning_contacts(): void
    {
        $this->settings(['password_recovery_sms_enabled' => true]);
        $user = User::factory()->create(['is_active' => true, 'username' => 'recovery-team', 'phone' => '+962791234567', 'phone_country_iso_code' => 'JO']);
        foreach ([' RECOVERY-TEAM ', strtoupper($user->email)] as $identifier) {
            $identity = $this->identify($identifier);
            $this->assertSame(['email', 'sms'], $identity['channels']);
            $this->assertSame(['recovery_token', 'channels', 'expires_in'], array_keys($identity));
            $this->assertSame(64, strlen($identity['recovery_token']));
        }
        Notification::assertNothingSent();
    }

    public function test_phone_number_does_not_identify_an_account_for_recovery(): void
    {
        $this->settings(['password_recovery_sms_enabled' => true]);
        User::factory()->create(['is_active' => true, 'username' => 'phone-owner', 'phone' => '+962791234567', 'phone_country_iso_code' => 'JO']);
        foreach (['+962791234567', '+962 79 123 4567'] as $identifier) {
            $identity = $this->identify($identifier);
            $challenge = $this->sendIdentified($identity, 'sms');
            $this->verifyChallenge($challenge, '123456')->assertUnprocessable();
        }
        Notification::assertNothingSent();
    }

    public function test_email_shaped_username_recovers_its_unique_owner_using_the_stored_contact(): void
    {
        $user = User::factory()->create([
            'is_active' => true,
            'username' => 'login.alias@example.com',
            'email' => 'delivery.address@example.com',
        ]);
        $challenge = $this->begin(' LOGIN.ALIAS@EXAMPLE.COM ');
        Notification::assertSentTo($user, PasswordRecoveryCodeNotification::class);
        Notification::assertCount(1);
        $this->resetCode($challenge, $this->code($user))->assertOk();
        $this->assertTrue(Hash::check('NewPassword123!', $user->refresh()->password));
    }

    public function test_normalized_username_and_email_resolve_the_account_not_a_decoy(): void
    {
        $user = User::factory()->create([
            'is_active' => true,
            'username' => 'Recovery.Team',
            'email' => 'Recovery.Owner@example.com',
        ]);
        $service = app(PasswordRecoveryServiceInterface::class);
        foreach ([' recovery.team ', ' RECOVERY.OWNER@EXAMPLE.COM '] as $identifier) {
            $identity = $this->identify($identifier);
            $context = $service->cache()->get($service->key('identity:'.$identity['recovery_token']));
            $this->assertSame($user->id, $context['user_id']);
        }
        Notification::assertNothingSent();
    }

    public function test_an_inactive_case_insensitive_collision_does_not_select_the_active_account(): void
    {
        User::factory()->create(['is_active' => true, 'email' => 'shared.owner@example.com']);
        User::factory()->create(['is_active' => false, 'username' => 'SHARED.OWNER@EXAMPLE.COM']);
        $identity = $this->identify('shared.owner@example.com');
        $service = app(PasswordRecoveryServiceInterface::class);
        $context = $service->cache()->get($service->key('identity:'.$identity['recovery_token']));
        $this->assertNull($context['user_id']);
        $this->assertNull($context['fingerprint']);
        $challenge = $this->sendIdentified($identity, 'email');
        $this->verifyChallenge($challenge, '123456')->assertUnprocessable();
        Notification::assertNothingSent();
    }

    public function test_matching_both_columns_on_the_same_account_is_not_ambiguous(): void
    {
        $user = User::factory()->create([
            'is_active' => true,
            'username' => 'same.owner@example.com',
            'email' => 'same.owner@example.com',
        ]);
        $challenge = $this->begin('SAME.OWNER@EXAMPLE.COM');
        Notification::assertSentTo($user, PasswordRecoveryCodeNotification::class);
        Notification::assertCount(1);
        $this->verifyChallenge($challenge, $this->code($user))->assertOk();
    }

    public function test_methods_require_both_user_contact_and_an_enabled_service(): void
    {
        $this->settings(['password_recovery_sms_enabled' => true]);
        $emailOnly = User::factory()->create(['is_active' => true, 'phone' => null]);
        $phoneOnly = User::factory()->create(['is_active' => true, 'email' => null, 'phone' => '+962791234567', 'phone_country_iso_code' => 'JO']);
        $both = User::factory()->create(['is_active' => true, 'phone' => '+962791234568', 'phone_country_iso_code' => 'JO']);
        $neither = User::factory()->create(['is_active' => true, 'email' => null, 'phone' => null]);
        $this->assertSame(['email'], $this->identify($emailOnly->username)['channels']);
        $this->assertSame(['email'], $this->identify($emailOnly->email)['channels']);
        $this->assertSame(['email', 'sms'], $this->identify($both->username)['channels']);
        $this->assertSame(['email', 'sms'], $this->identify($both->email)['channels']);
        $this->assertSame([], $this->identify($neither->username)['channels']);
        $this->travel(61)->seconds();
        $this->assertSame(['sms'], $this->identify($phoneOnly->username)['channels']);
        $this->settings(['password_recovery_sms_enabled' => true, 'services.notifications.sms.enabled' => false]);
        $this->assertSame([], $this->identify($phoneOnly->username)['channels']);
        $this->assertSame(['email'], $this->identify($both->username)['channels']);
        $this->settings(['password_recovery_sms_enabled' => true, 'services.notifications.email.enabled' => false]);
        $this->assertSame(['sms'], $this->identify($both->username)['channels']);
        $this->sendIdentified($this->identify($phoneOnly->username), 'sms');
        Notification::assertSentTo($phoneOnly, PasswordRecoveryCodeNotification::class);
    }

    public function test_identifier_code_verification_precedes_a_single_use_password_reset(): void
    {
        $user = User::factory()->create(['is_active' => true]);
        $user->createToken('browser');
        $password = $user->password;
        $identity = $this->identify($user->username);
        $challenge = $this->sendIdentified($identity, 'email');
        $code = $this->code($user);
        $grant = $this->verifyChallenge($challenge, $code)->assertOk()->json('body');
        $this->assertSame($password, $user->refresh()->password);
        $this->assertSame(1, $user->tokens()->count());
        $this->verifyChallenge($challenge, $code)->assertUnprocessable();
        $this->resetGrant($grant)->assertOk()->assertJsonPath('body.success', true);
        $this->assertTrue(Hash::check('NewPassword123!', $user->refresh()->password));
        $this->assertSame(0, $user->tokens()->count());
        $this->resetGrant($grant)->assertUnprocessable();
    }

    public function test_verification_attempts_are_shared_with_the_legacy_reset_endpoint(): void
    {
        $user = User::factory()->create(['is_active' => true]);
        $challenge = $this->sendIdentified($this->identify($user->username), 'email');
        for ($i = 0; $i < 5; $i++) {
            $this->verifyChallenge($challenge, '000000')->assertUnprocessable();
        }
        $this->verifyChallenge($challenge, $this->code($user))->assertUnprocessable();
        $this->resetCode($challenge, $this->code($user))->assertUnprocessable();
        $this->resetGrant(['reset_token' => str_repeat('a', 64)])->assertUnprocessable();
    }

    public function test_reset_grant_expires_at_the_original_code_expiry(): void
    {
        $user = User::factory()->create(['is_active' => true]);
        $challenge = $this->sendIdentified($this->identify($user->username), 'email');
        $this->travel(550)->seconds();
        $grant = $this->verifyChallenge($challenge, $this->code($user))->assertOk()->assertJsonPath('body.expires_in', 50)->json('body');
        $this->travel(51)->seconds();
        $this->resetGrant($grant)->assertUnprocessable();
    }

    public function test_resend_invalidates_a_previously_verified_grant_and_uses_destination_cooldown(): void
    {
        $user = User::factory()->create(['is_active' => true]);
        $identity = $this->identify($user->username);
        $challenge = $this->sendIdentified($identity, 'email');
        $grant = $this->verifyChallenge($challenge, $this->code($user))->assertOk()->json('body');
        $otherIdentity = $this->identify($user->email);
        $this->postJson('/api/v1/auth/password-recovery/code', ['recovery_token' => $otherIdentity['recovery_token'], 'channel' => 'email'])->assertStatus(429);
        $this->travel(61)->seconds();
        $this->sendIdentified($otherIdentity, 'email');
        $this->resetGrant($grant)->assertUnprocessable();
    }

    public function test_account_and_service_changes_invalidate_identified_delivery_and_verified_grants(): void
    {
        $user = User::factory()->create(['is_active' => true]);
        $identity = $this->identify($user->username);
        $challenge = $this->sendIdentified($identity, 'email');
        $grant = $this->verifyChallenge($challenge, $this->code($user))->assertOk()->json('body');
        $this->settings(['services.notifications.email.enabled' => false]);
        $this->resetGrant($grant)->assertUnprocessable();
        $this->settings();
        $user->forceFill(['email' => 'changed@example.com'])->save();
        $this->resetGrant($grant)->assertUnprocessable();
        Notification::fake();
        $this->sendIdentified($identity, 'email');
        Notification::assertNothingSent();
    }

    public function test_unknown_inactive_and_ambiguous_identifiers_get_decoy_contexts_and_no_delivery(): void
    {
        $inactive = User::factory()->create(['is_active' => false]);
        User::factory()->create(['is_active' => true, 'email' => 'shared@example.com']);
        User::factory()->create(['is_active' => true, 'username' => 'shared@example.com']);
        foreach (['unknown@example.com', $inactive->username, 'shared@example.com'] as $identifier) {
            $identity = $this->identify($identifier);
            $this->assertSame(['email'], $identity['channels']);
            $challenge = $this->sendIdentified($identity, 'email');
            $this->verifyChallenge($challenge, '123456')->assertUnprocessable();
        }
        Notification::assertNothingSent();
    }

    public function test_identified_request_rejects_unoffered_methods_and_expired_contexts(): void
    {
        $this->settings(['password_recovery_sms_enabled' => true]);
        $user = User::factory()->create(['is_active' => true, 'phone' => null]);
        $identity = $this->identify($user->username);
        $this->postJson('/api/v1/auth/password-recovery/code', ['recovery_token' => $identity['recovery_token'], 'channel' => 'sms'])->assertUnprocessable();
        $this->travel(601)->seconds();
        $this->postJson('/api/v1/auth/password-recovery/code', ['recovery_token' => $identity['recovery_token'], 'channel' => 'email'])->assertUnprocessable();
        Notification::assertNothingSent();
    }

    public function test_sending_a_code_requires_an_identified_account_context(): void
    {
        foreach (['email' => 'someone@example.com', 'sms' => '+962791234567'] as $channel => $destination) {
            $this->postJson('/api/v1/auth/password-recovery/code', compact('channel', 'destination'))
                ->assertUnprocessable()->assertJsonValidationErrors('recovery_token');
        }
        Notification::assertNothingSent();
    }

    public function test_extra_destination_cannot_override_the_identified_account(): void
    {
        $user = User::factory()->create(['is_active' => true]);
        $identity = $this->identify($user->username);
        $this->postJson('/api/v1/auth/password-recovery/code', [
            'recovery_token' => $identity['recovery_token'],
            'channel' => 'email',
            'destination' => 'other@example.com',
        ])->assertOk();
        Notification::assertSentTo($user, PasswordRecoveryCodeNotification::class);
        Notification::assertCount(1);
    }

    private function identify(string $identifier): array
    {
        return $this->postJson('/api/v1/auth/password-recovery/identify', compact('identifier'))->assertOk()->assertHeader('Cache-Control', 'no-store, private')->json('body');
    }

    private function sendIdentified(array $identity, string $channel): array
    {
        return $this->postJson('/api/v1/auth/password-recovery/code', ['recovery_token' => $identity['recovery_token'], 'channel' => $channel])->assertOk()->assertHeader('Cache-Control', 'no-store, private')->json('body');
    }

    private function verifyChallenge(array $challenge, string $code): \Illuminate\Testing\TestResponse
    {
        return $this->postJson('/api/v1/auth/password-recovery/verify', ['challenge_token' => $challenge['challenge_token'], 'code' => $code]);
    }

    private function resetGrant(array $grant): \Illuminate\Testing\TestResponse
    {
        return $this->postJson('/api/v1/auth/password-recovery/reset', ['reset_token' => $grant['reset_token'], 'password' => 'NewPassword123!', 'password_confirmation' => 'NewPassword123!']);
    }

    private function begin(string $identifier, string $channel = 'email'): array
    {
        return $this->sendIdentified($this->identify($identifier), $channel);
    }

    private function resetCode(array $challenge, string $code): \Illuminate\Testing\TestResponse
    {
        return $this->postJson('/api/v1/auth/password-recovery/reset', ['challenge_token' => $challenge['challenge_token'], 'code' => $code, 'password' => 'NewPassword123!', 'password_confirmation' => 'NewPassword123!']);
    }

    private function code(User $user): string
    {
        return (new ReflectionProperty(PasswordRecoveryCodeNotification::class, 'code'))->getValue(Notification::sent($user, PasswordRecoveryCodeNotification::class)->last());
    }

    private function settings(array $overrides = []): void
    {
        $settings = [
            'password_recovery_enabled' => true,
            'password_recovery_email_enabled' => true,
            'password_recovery_sms_enabled' => false,
            'services.notifications.email.enabled' => true,
            'services.notifications.email.mailer' => 'smtp',
            'services.notifications.email.smtp.host' => 'smtp.example.com',
            'services.notifications.email.smtp.port' => 587,
            'services.notifications.email.from_address' => 'support@example.com',
            'services.notifications.sms.enabled' => true,
            'services.notifications.sms.provider' => 'twilio',
            'services.notifications.sms.sender_name' => 'Forkiva',
            'services.notifications.sms.twilio.sid' => 'test-sid',
            'services.notifications.sms.twilio.token' => 'test-token',
            ...$overrides,
        ];
        \Modules\Setting\Models\Setting::setMany($settings);
        app(\Modules\Setting\Services\Setting\SettingServiceInterface::class)->refreshSettingBinding();
    }
}
