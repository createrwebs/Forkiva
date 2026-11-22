<?php

namespace Modules\Setting\Models;

use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\QueryException;
use Illuminate\Encryption\MissingAppKeyException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Modules\Setting\Events\SettingSaved;
use Modules\Support\Eloquent\Model;

class Setting extends Model
{
    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = ['key', 'is_translatable', 'is_encryptable', 'payload'];

    /**
     * The event map for the model.
     *
     * @var array
     */
    protected $dispatchesEvents = [
        'saved' => SettingSaved::class,
    ];

    /**
     * Get all settings from cache without exposing decrypted payloads.
     *
     * @return Collection<string, array{payload: mixed, is_encryptable: bool, is_translatable: bool}>
     */
    public static function allCached(): Collection
    {
        try {
            return Cache::tags('settings')->rememberForever(
                makeCacheKey(['settings', 'list'], false),
                fn () => self::query()
                    ->get(['key', 'payload', 'is_encryptable', 'is_translatable'])
                    ->mapWithKeys(static fn (self $setting): array => [
                        $setting->key => [
                            'payload' => $setting->getRawOriginal('payload'),
                            'is_encryptable' => (bool) $setting->getRawOriginal('is_encryptable'),
                            'is_translatable' => (bool) $setting->getRawOriginal('is_translatable'),
                        ],
                    ])
            );
        } catch (QueryException) {
            return collect();
        }
    }

    /**
     * Get setting for the given key.
     */
    public static function get(string $key, mixed $default = null): string|array|null
    {
        try {
            return static::where('key', $key)->first()?->payload ?: $default;
        } catch (QueryException) {
            return $default;
        }
    }

    /**
     * Determine if the given setting key exists.
     */
    public static function has(string $key): bool
    {
        return static::where('key', $key)->exists();
    }

    /**
     * Set the given settings.
     */
    public static function setMany(array $settings): void
    {
        foreach ($settings as $key => $payload) {
            self::set($key, $payload);
        }
    }

    /**
     * Set the given setting.
     */
    public static function set(string $key, mixed $payload): void
    {
        if ($key === 'encryptable') {
            static::setEncryptableSettings($payload);
        } elseif ($key === 'translatable') {
            static::setTranslatableSettings($payload);
        } else {
            static::updateOrCreate(['key' => $key], ['payload' => $payload]);
        }
    }

    /**
     * Set an encryptable settings.
     */
    public static function setEncryptableSettings(array $settings = []): void
    {
        foreach ($settings as $key => $payload) {
            if ($key === 'translatable') {
                static::setTranslatableSettings($payload, true);
            } else {
                static::updateOrCreate(['key' => $key], ['is_encryptable' => true, 'payload' => $payload]);
            }
        }
    }

    /**
     * Set a translatable settings.
     */
    public static function setTranslatableSettings(array $settings = [], bool $encryptable = false): void
    {
        foreach ($settings as $key => $payload) {
            $oldSetting = static::where('key', $key)->first();
            $oldPayload = $oldSetting
                ? static::decodeStoredPayload(
                    $oldSetting->getRawOriginal('payload'),
                    (bool) $oldSetting->is_encryptable
                )
                : null;
            $oldPayload = is_array($oldPayload) ? $oldPayload : [];
            static::updateOrCreate(['key' => $key], [
                'is_translatable' => true,
                'is_encryptable' => $encryptable,
                'payload' => array_merge($oldPayload, [locale() => $payload]),
            ]);
        }
    }

    /**
     * Decode a payload from its database representation.
     *
     * @param  mixed  $payload  Raw encrypted or serialized payload
     * @param  bool  $encryptable  Whether the payload is encrypted
     * @return mixed The decoded payload, or null when encrypted data cannot be decrypted
     */
    public static function decodeStoredPayload(mixed $payload, bool $encryptable): mixed
    {
        if ($payload === null) {
            return null;
        }

        if ($encryptable) {
            try {
                return decrypt($payload);
            } catch (DecryptException|MissingAppKeyException) {
                return null;
            }
        }

        return unserialize($payload, ['allowed_classes' => false]);
    }

    /**
     * Decode one raw cached setting payload at the point of access.
     *
     * @param  array{payload?: mixed, is_encryptable?: bool, is_translatable?: bool}  $setting
     * @return mixed The decoded and localized payload
     */
    public static function decodeCachedPayload(array $setting): mixed
    {
        $payload = static::decodeStoredPayload(
            $setting['payload'] ?? null,
            (bool) ($setting['is_encryptable'] ?? false)
        );

        if (! ($setting['is_translatable'] ?? false) || ! is_array($payload)) {
            return $payload;
        }

        return $payload[locale()] ?? $payload[fallbackLocale()] ?? null;
    }

    /**
     * Set the payload of the setting.
     */
    public function payload(): Attribute
    {
        return Attribute::make(
            get: function ($payload) {
                $payload = static::decodeStoredPayload($payload, $this->is_encryptable);

                if ($this->is_translatable && is_array($payload)) {
                    $payload = $payload[locale()] ?? $payload[fallbackLocale()] ?? null;
                }

                return $payload;
            },
            set: function ($payload) {
                if ($this->is_encryptable) {
                    return encrypt($payload);
                } else {
                    return serialize($payload);
                }
            }
        );
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_translatable' => 'boolean',
            'is_encryptable' => 'boolean',
        ];
    }
}
