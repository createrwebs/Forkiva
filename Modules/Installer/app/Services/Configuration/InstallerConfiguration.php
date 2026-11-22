<?php

namespace Modules\Installer\Services\Configuration;

use Dotenv\Dotenv;
use Illuminate\Encryption\Encrypter;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Modules\Currency\Currency;
use Modules\Currency\Models\CurrencyRate;
use Modules\Installer\Services\Environment\InstallerEnvironmentInterface;
use Modules\Setting\Models\Setting;
use Modules\Setting\Services\Setting\SettingServiceInterface;
use Modules\Support\Country;
use Modules\Support\Locale;
use Modules\Support\TimeZone;
use RuntimeException;
use Throwable;

readonly class InstallerConfiguration implements InstallerConfigurationInterface
{
    /**
     * Create the configuration service with environment persistence.
     */
    public function __construct(private InstallerEnvironmentInterface $environment) {}

    /** {@inheritDoc} */
    public function options(): array
    {
        return [
            'countries' => Country::toList(),
            'locales' => Locale::toList(),
            'currencies' => Currency::toList(),
            'timezones' => TimeZone::toList(),
        ];
    }

    /** {@inheritDoc} */
    public function persist(array $data): void
    {
        $path = app()->environmentFilePath();
        $existing = is_file($path) ? Dotenv::parse(file_get_contents($path)) : [];
        $values = [
            'APP_NAME' => $data['app_name'], 'APP_URL' => $data['app_url'],
            'APP_LOCALE' => $data['default_locale'], 'APP_FALLBACK_LOCALE' => $data['default_locale'],
            'PASSKEYS_ENABLED' => $data['passkeys_enabled'] ? 'true' : 'false',
            'PASSKEYS_RELYING_PARTY_ID' => $data['passkeys_relying_party_id'] ?? '',
            'PASSKEYS_ALLOWED_ORIGINS' => implode(',', $data['passkeys_allowed_origins']),
            'CACHE_ENABLED' => $data['cache_enabled'] ? 'true' : 'false',
            'CACHE_STORE' => $data['cache_store'],
            'PASSKEYS_CACHE_STORE' => $data['cache_store'],
            'TWO_FACTOR_CACHE_STORE' => $data['cache_store'],
            'PASSWORD_RECOVERY_CACHE_STORE' => $data['cache_store'],
            'FIREBASE_CACHE_STORE' => $data['cache_store'],
            'APP_MAINTENANCE_STORE' => $data['cache_store'],
            'SESSION_STORE' => $data['cache_store'],
            'CART_MUTATION_LOCK_STORE' => 'file',
        ];
        if (($existing['INSTALLER_KEYS_GENERATED'] ?? '') !== 'true') {
            $values['APP_KEY'] = 'base64:'.base64_encode(Encrypter::generateKey(config('app.cipher')));
            $values['VITE_STORAGE_ENCRYPTION_KEY'] = base64_encode(random_bytes(32));
            // Laravel can decrypt the current installer cookie across the next request's key change.
            $values['APP_PREVIOUS_KEYS'] = implode(',', array_unique(array_filter([
                $existing['APP_KEY'] ?? config('app.key'),
                ...explode(',', $existing['APP_PREVIOUS_KEYS'] ?? ''),
            ])));
            $values['INSTALLER_KEYS_GENERATED'] = 'true';
        }
        $this->environment->write($values);
    }

    /** {@inheritDoc} */
    public function apply(array $data): void
    {
        Setting::setMany(Arr::only($data, [
            'supported_countries',
            'default_country',
            'supported_locales',
            'default_locale',
            'default_timezone',
            'supported_currencies',
            'default_currency',
            'passkeys_enabled',
            'passkeys_relying_party_id',
            'passkeys_allowed_origins',
        ]));

        Setting::query()
            ->updateOrCreate(
                ['key' => 'app_name'],
                [
                    'is_translatable' => true,
                    'is_encryptable' => false,
                    'payload' => array_fill_keys($data['supported_locales'], $data['app_name']),
                ]
            );

        foreach ($data['supported_currencies'] as $currency) {
            CurrencyRate::query()->firstOrCreate(['currency' => $currency], ['rate' => 1]);
        }

        app(SettingServiceInterface::class)->refreshSettingBinding();
    }

    /** {@inheritDoc} */
    public function verifyCache(string $store): void
    {
        $key = 'installer-probe:'.Str::uuid();
        $cache = Cache::store($store);
        $lock = $cache->lock($key.':lock', 10);
        $acquired = false;
        try {
            $cache->put($key, 'ready', 30);
            if ($cache->get($key) !== 'ready' || ! ($acquired = $lock->get())) {
                throw new RuntimeException('Cache probe failed.');
            }
            $tagged = $cache->tags([$key.':tag']);
            $tagged->put($key.':value', 'tagged', 30);
            if ($tagged->get($key.':value') !== 'tagged') {
                throw new RuntimeException('Cache tags cannot persist values.');
            }
            $tagged->flush();
            if ($tagged->has($key.':value') || $cache->get($key) !== 'ready') {
                throw new RuntimeException('Cache tag invalidation failed.');
            }
            $contender = $cache->lock($key.':lock', 10);
            if ($contender->get()) {
                $contender->release();
                throw new RuntimeException('Cache lock is not exclusive.');
            }
        } catch (Throwable) {
            throw ValidationException::withMessages(['cache_store' => 'The selected cache cannot persist values and acquire exclusive locks. Check its table or filesystem permissions.']);
        } finally {
            if ($acquired) {
                $lock->release();
            }
            $cache->forget($key);
        }
    }
}
