<?php

namespace Modules\Setting\Repositories;

use ArrayAccess;
use Illuminate\Support\Collection;
use Modules\Setting\Models\Setting;

class SettingRepository implements ArrayAccess
{
    /**
     * Collection of raw cached settings.
     *
     * @var Collection<string, array{payload: mixed, is_encryptable: bool, is_translatable: bool}>
     */
    private Collection $settings;

    /**
     * Create a new repository instance.
     */
    public function __construct(Collection $settings)
    {
        $this->settings = $settings;
    }

    /**
     * Label for the resource.
     */
    public function label(): string
    {
        return __('setting::settings.settings.setting');
    }

    /**
     * Get all settings.
     */
    public function all(array $except = []): array
    {
        return $this->settings
            ->except($except)
            ->map(static fn (array $setting): mixed => Setting::decodeCachedPayload($setting))
            ->all();
    }

    /**
     * Determine if a setting is existing.
     */
    public function offsetExists(mixed $offset): bool
    {
        return $this->settings->has($offset);
    }

    /**
     * Unset a setting by the given key.
     */
    public function offsetUnset(mixed $offset): void
    {
        $this->settings->forget($offset);
    }

    /**
     * Get setting for the given key.
     *
     * @return mixed
     */
    public function __get(mixed $offset)
    {
        return $this->offsetGet($offset);
    }

    /**
     * Set a offset / value setting pair.
     *
     * @return void
     */
    public function __set(mixed $offset, mixed $value)
    {
        $this->offsetSet($offset, $value);
    }

    /**
     * Get setting for the given offset.
     */
    public function offsetGet(mixed $offset): mixed
    {
        return $this->get($offset);
    }

    /**
     * Get setting for the given key.
     */
    public function get(mixed $offset, mixed $default = null): mixed
    {
        if (! $this->settings->has($offset)) {
            return $default;
        }

        return Setting::decodeCachedPayload($this->settings->get($offset));
    }

    /**
     * Set a key / value setting pair.
     */
    public function offsetSet(mixed $offset, mixed $value): void
    {
        $this->set([$offset => $value]);
    }

    /**
     * Set the given settings.
     */
    public function set(array $settings = []): void
    {
        Setting::setMany($settings);
    }
}
