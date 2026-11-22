<?php

namespace Modules\Support\Tests\Unit;

use Modules\Support\Security\PasskeyConfigurationValidator;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class PasskeyConfigurationValidatorTest extends TestCase
{
    #[DataProvider('validConfigurations')]
    public function test_it_accepts_secure_matching_configurations(string $relyingPartyId, array $origins): void
    {
        $this->assertTrue(PasskeyConfigurationValidator::valid($relyingPartyId, $origins));
    }

    public static function validConfigurations(): array
    {
        return [
            'production domain and subdomain' => [
                'example.com',
                ['https://example.com', 'https://admin.example.com:8443'],
            ],
            'localhost development' => [
                'localhost',
                ['http://localhost:3101'],
            ],
            'loopback development' => [
                '127.0.0.1',
                ['http://127.0.0.1:3101'],
            ],
        ];
    }

    #[DataProvider('invalidConfigurations')]
    public function test_it_rejects_insecure_or_mismatched_configurations(string $relyingPartyId, array $origins): void
    {
        $this->assertFalse(PasskeyConfigurationValidator::valid($relyingPartyId, $origins));
    }

    public static function invalidConfigurations(): array
    {
        return [
            'insecure production origin' => ['example.com', ['http://example.com']],
            'unrelated origin' => ['example.com', ['https://example.net']],
            'lookalike domain' => ['example.com', ['https://example.com.attacker.test']],
            'origin with path' => ['example.com', ['https://example.com/login']],
            'origin with credentials' => ['example.com', ['https://user@example.com']],
            'empty origins' => ['example.com', []],
            'invalid ipv4 address' => ['999.999.999.999', ['https://999.999.999.999']],
            'public suffix or single label' => ['com', ['https://example.com']],
        ];
    }
}
