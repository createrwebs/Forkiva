<?php

namespace Modules\User\Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Passkeys\Passkey;
use Laravel\Sanctum\Sanctum;
use Modules\Setting\Repositories\SettingRepository;
use Modules\User\Models\User;
use Tests\TestCase;

class PasskeyAuthTest extends TestCase
{
    use RefreshDatabase;

    public function test_passkey_login_options_are_available_to_guests(): void
    {
        $this->getJson('/api/v1/auth/passkeys/options')
            ->assertOk()
            ->assertJsonStructure([
                'body' => [
                    'options' => [
                        'challenge',
                        'rpId',
                        'userVerification',
                    ],
                ],
            ]);
    }

    public function test_passkey_endpoints_are_unavailable_when_disabled_by_an_administrator(): void
    {
        app()->instance('setting', new SettingRepository(collect([
            'passkeys_enabled' => [
                'payload' => serialize(false),
                'is_encryptable' => false,
                'is_translatable' => false,
            ],
        ])));

        $this->getJson('/api/v1/auth/passkeys/options')->assertNotFound();
    }

    public function test_passkey_endpoints_fail_closed_for_an_invalid_relying_party_configuration(): void
    {
        app()->instance('setting', new SettingRepository(collect([
            'passkeys_enabled' => true,
            'passkeys_relying_party_id' => 'example.com',
            'passkeys_allowed_origins' => ['http://example.com'],
        ])->map(fn (mixed $value): array => [
            'payload' => serialize($value),
            'is_encryptable' => false,
            'is_translatable' => false,
        ])));

        $this->getJson('/api/v1/auth/passkeys/options')->assertNotFound();
    }

    public function test_passkey_login_rejects_a_malformed_credential_without_authentication(): void
    {
        $this->postJson('/api/v1/auth/passkeys/login', [
            'credential' => [],
        ])->assertUnprocessable()
            ->assertJsonValidationErrors([
                'credential.id',
                'credential.rawId',
                'credential.type',
                'credential.response',
            ]);
    }

    public function test_passkey_management_requires_authentication(): void
    {
        $this->getJson('/api/v1/accounts/passkeys')->assertUnauthorized();
        $this->postJson('/api/v1/accounts/passkeys/options')->assertUnauthorized();
        $this->postJson('/api/v1/accounts/passkeys')->assertUnauthorized();
        $this->deleteJson('/api/v1/accounts/passkeys/1')->assertUnauthorized();
    }

    public function test_user_can_list_and_delete_only_their_own_passkeys(): void
    {
        $user = User::factory()->create(['is_active' => true]);
        $otherUser = User::factory()->create(['is_active' => true]);
        $passkey = $this->createPasskey($user, 'Work laptop', 'credential-one');
        $otherPasskey = $this->createPasskey($otherUser, 'Other laptop', 'credential-two');

        Sanctum::actingAs($user);

        $this->getJson('/api/v1/accounts/passkeys')
            ->assertOk()
            ->assertJsonCount(1, 'body.passkeys')
            ->assertJsonPath('body.passkeys.0.id', $passkey->id)
            ->assertJsonPath('body.passkeys.0.name', 'Work laptop');

        $this->deleteJson("/api/v1/accounts/passkeys/{$otherPasskey->id}")
            ->assertForbidden();

        $this->deleteJson("/api/v1/accounts/passkeys/{$passkey->id}")
            ->assertOk()
            ->assertJsonPath('body.success', true);

        $this->assertDatabaseMissing('passkeys', ['id' => $passkey->id]);
        $this->assertDatabaseHas('passkeys', ['id' => $otherPasskey->id]);
    }

    private function createPasskey(User $user, string $name, string $credentialId): Passkey
    {
        return $user->passkeys()->create([
            'name' => $name,
            'credential_id' => $credentialId,
            'credential' => [],
        ]);
    }
}
