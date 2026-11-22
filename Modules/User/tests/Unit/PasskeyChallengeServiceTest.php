<?php

namespace Modules\User\Tests\Unit;

use Illuminate\Support\Facades\Cache;
use Illuminate\Validation\ValidationException;
use Laravel\Passkeys\Actions\GenerateVerificationOptions;
use Modules\User\Services\Auth\PasskeyChallengeService;
use ParagonIE\ConstantTime\Base64UrlSafe;
use Tests\TestCase;
use Webauthn\AuthenticatorAssertionResponse;
use Webauthn\AuthenticatorData;
use Webauthn\CollectedClientData;
use Webauthn\PublicKeyCredential;

class PasskeyChallengeServiceTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config()->set('passkeys.cache_store', 'array');
    }

    public function test_challenge_uses_the_configured_persistent_cache_store(): void
    {
        config()->set('cache.default', 'array');
        config()->set('passkeys.cache_store', 'file');

        $service = app(PasskeyChallengeService::class);
        $options = app(GenerateVerificationOptions::class)();
        $credential = $this->credentialForChallenge($options->challenge);

        $service->storeLogin($options);
        Cache::store('array')->flush();

        $this->assertSame(
            $options->challenge,
            $service->pullLogin($credential)->challenge
        );
    }

    public function test_login_challenge_can_only_be_consumed_once(): void
    {
        $service = app(PasskeyChallengeService::class);
        $options = app(GenerateVerificationOptions::class)();
        $credential = $this->credentialForChallenge($options->challenge);

        $service->storeLogin($options);

        $this->assertSame(
            $options->challenge,
            $service->pullLogin($credential)->challenge
        );

        $this->expectException(ValidationException::class);

        $service->pullLogin($credential);
    }

    private function credentialForChallenge(string $challenge): PublicKeyCredential
    {
        $clientData = CollectedClientData::create('', [
            'type' => 'webauthn.get',
            'challenge' => Base64UrlSafe::encodeUnpadded($challenge),
            'origin' => config('passkeys.allowed_origins')[0],
        ]);
        $authenticatorData = AuthenticatorData::create('', '', "\x05", 0);
        $response = AuthenticatorAssertionResponse::create(
            $clientData,
            $authenticatorData,
            'signature'
        );

        return PublicKeyCredential::create('public-key', 'credential-id', $response);
    }
}
