<?php

namespace Modules\User\Services\Auth;

use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Log;
use Modules\Support\Security\PasskeyConfigurationValidator;

class PasskeyConfiguration
{
    /**
     * Apply administrator-managed passkey settings for the current request.
     */
    public function apply(): void
    {
        $origins = setting('passkeys_allowed_origins', config('passkeys.allowed_origins', []));

        $enabled = (bool) setting('passkeys_enabled', config('passkeys.enabled', true));
        $relyingPartyId = strtolower((string) setting(
            'passkeys_relying_party_id',
            config('passkeys.relying_party_id')
        ));
        $origins = is_array($origins) ? array_values($origins) : [];
        $configurationValid = PasskeyConfigurationValidator::valid($relyingPartyId, $origins);

        if ($enabled && ! $configurationValid) {
            Log::warning('Passkeys were disabled because the relying-party configuration is invalid.');
        }

        Config::set([
            'passkeys.enabled' => $enabled && $configurationValid,
            'passkeys.relying_party_id' => $relyingPartyId,
            'passkeys.allowed_origins' => $origins,
            'passkeys.timeout' => (int) setting('passkeys_timeout', config('passkeys.timeout', 60000)),
        ]);
    }

    /**
     * Determine whether passkey endpoints are enabled.
     */
    public function enabled(): bool
    {
        return Config::boolean('passkeys.enabled');
    }
}
