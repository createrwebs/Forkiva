<?php

namespace Modules\Sms\Services\SmsProvider;

use Illuminate\Support\Arr;
use Modules\Sms\Enums\SmsProvider;

class SmsProviderConfiguratorService implements SmsProviderConfiguratorServiceInterface
{
    /**
     * Setting fields mapped to the package driver configuration keys.
     *
     * @var array<string, array<string, string>>
     */
    private const PROVIDER_FIELDS = [
        'log' => [
            'channel' => 'channel',
        ],
        'jawal_sms' => [
            'username' => 'username',
            'password' => 'password',
        ],
        'taqnyat' => [
            'authorization' => 'authorization',
        ],
        'nexmo' => [
            'api_key' => 'api_key',
            'api_secret' => 'api_secret',
        ],
        'twilio' => [
            'sid' => 'sid',
            'token' => 'token',
        ],
        'morasa' => [
            'username' => 'username',
            'api_key' => 'api_key',
        ],
        'msegat' => [
            'username' => 'username',
            'api_key' => 'api_key',
        ],
        'kobikom' => [
            'api_key' => 'api_key',
        ],
        'unifonic' => [
            'sid' => 'sid',
            'message_type' => 'message_type',
        ],
        'jor_mall' => [
            'acc_name' => 'acc_name',
            'password' => 'password',
        ],
    ];

    /** {@inheritDoc} */
    public function activeProvider(): ?SmsProvider
    {
        if (! filter_var(setting('services.notifications.sms.enabled', false), FILTER_VALIDATE_BOOLEAN)) {
            return null;
        }

        $provider = SmsProvider::tryFrom(
            (string) setting('services.notifications.sms.provider', '')
        );

        if (! $provider) {
            return null;
        }

        $this->configure($provider);

        return $provider;
    }

    /** {@inheritDoc} */
    public function senderName(): ?string
    {
        $sender = setting('services.notifications.sms.sender_name');

        return blank($sender) ? null : (string) $sender;
    }

    /**
     * Apply service settings to prgayman/laravel-sms.
     */
    protected function configure(SmsProvider $provider): void
    {
        $providerKey = $provider->value;
        $driverConfig = (array) config("sms.drivers.$providerKey", []);
        $driverConfig['driver'] = $providerKey;

        foreach (self::PROVIDER_FIELDS[$providerKey] ?? [] as $settingKey => $configKey) {
            $value = setting("services.notifications.sms.$providerKey.$settingKey");

            if ($value !== null && $value !== '') {
                Arr::set($driverConfig, $configKey, $value);
            }
        }

        if ($sender = $this->senderName()) {
            $driverConfig[$provider === SmsProvider::JorMall ? 'sender_id' : 'sender'] = $sender;
        }

        config([
            'sms.default' => $providerKey,
            "sms.drivers.$providerKey" => $driverConfig,
        ]);
    }
}
