<?php

namespace Modules\UserPreference\Services\UserPreference;

use BackedEnum;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use Modules\User\Models\User;
use Modules\UserPreference\Models\UserPreference;
use Modules\UserPreference\Support\PreferenceIdentifier;
use Modules\UserPreference\Support\PreferenceValueCodec;

final readonly class UserPreferenceService implements UserPreferenceServiceInterface
{
    public function __construct(private PreferenceValueCodec $codec) {}

    /** {@inheritDoc} */
    public function all(User $user, ?string $locale = null, ?string $prefix = null): array
    {
        $preferences = [];
        $query = $this->query($user);

        if ($prefix !== null) {
            $query->where('identifier', 'like', addcslashes($prefix, '%_\\').'%');
        }

        /** @var UserPreference $row */
        foreach ($query->orderBy('identifier')->cursor() as $row) {
            $preferences[$row->identifier] = $this->resolve($row, null, $locale);
        }

        return $preferences;
    }

    /**
     * Scope persistence to a saved user without relying on ambient authentication.
     *
     * @return Builder<UserPreference>
     */
    private function query(User $user): Builder
    {
        $this->assertPersistedUser($user);

        return UserPreference::query()->where('user_id', $user->getKey());
    }

    /**
     * Reject users without a database identity before querying or writing preferences.
     *
     * @throws InvalidArgumentException
     */
    private function assertPersistedUser(User $user): void
    {
        if (! $user->exists) {
            throw new InvalidArgumentException('User must be persisted.');
        }
    }

    /**
     * Decode a stored value and resolve translations through the requested and fallback locales.
     *
     * An explicit null translation is retained instead of falling back.
     */
    private function resolve(UserPreference $row, mixed $default, ?string $locale): mixed
    {
        $value = $this->codec->decode($row->payload, $row->is_encrypted, $row->identifier);

        if (! $row->is_translatable) {
            return $value;
        }

        if (! is_array($value)) {
            return $default;
        }

        $locale ??= app()->getLocale();

        if (array_key_exists($locale, $value)) {
            return $value[$locale];
        }

        $fallbackLocale = config('app.fallback_locale');

        return array_key_exists($fallbackLocale, $value) ? $value[$fallbackLocale] : $default;
    }

    /** {@inheritDoc} */
    public function set(
        User $user,
        string|BackedEnum $identifier,
        mixed $value,
        bool $encrypted = false,
        bool $translatable = false,
        ?string $locale = null,
    ): void {
        $this->setMany(
            $user,
            [PreferenceIdentifier::normalize($identifier) => $value],
            $encrypted,
            $translatable,
            $locale,
        );
    }

    /** {@inheritDoc} */
    public function setMany(
        User $user,
        array $preferences,
        bool $encrypted = false,
        bool $translatable = false,
        ?string $locale = null,
    ): void {
        if ($preferences === []) {
            return;
        }

        $this->assertPersistedUser($user);

        $normalized = [];
        foreach ($preferences as $identifier => $value) {
            $normalized[PreferenceIdentifier::normalize($identifier)] = $value;
        }
        ksort($normalized);

        if ($locale !== null) {
            $this->assertLocale($locale);
        }

        DB::transaction(function () use ($user, $normalized, $encrypted, $translatable, $locale): void {
            // Lock the user row first so concurrent first writes cannot downgrade protected values.
            User::withoutGlobalScopes()
                ->select($user->getKeyName())
                ->whereKey($user->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            $existing = $this->query($user)
                ->whereIn('identifier', array_keys($normalized))
                ->orderBy('identifier')
                ->lockForUpdate()
                ->get()
                ->keyBy('identifier');

            $timestamp = now();
            $rows = [];

            foreach ($normalized as $identifier => $value) {
                /** @var UserPreference|null $current */
                $current = $existing->get($identifier);
                $shouldTranslate = $translatable || ($current?->is_translatable ?? false);
                $shouldEncrypt = $encrypted || ($current?->is_encrypted ?? false);

                if ($shouldTranslate) {
                    $value = $this->mergeTranslations($current, $value, $locale);
                }

                $rows[] = [
                    'user_id' => $user->getKey(),
                    'identifier' => $identifier,
                    'is_translatable' => $shouldTranslate,
                    'is_encrypted' => $shouldEncrypt,
                    'payload' => $this->codec->encode($value, $shouldEncrypt),
                    'created_at' => $current?->created_at ?? $timestamp,
                    'updated_at' => $timestamp,
                ];
            }

            UserPreference::query()->upsert(
                $rows,
                ['user_id', 'identifier'],
                ['is_translatable', 'is_encrypted', 'payload', 'updated_at'],
            );
        });
    }

    /**
     * Restrict stored locale keys to language tags supported by the preference format.
     *
     * @throws InvalidArgumentException
     */
    private function assertLocale(string $locale): void
    {
        if (preg_match('/^[a-z]{2,3}(?:[-_][A-Za-z0-9]{2,8})*$/', $locale) !== 1) {
            throw new InvalidArgumentException('Invalid preference locale.');
        }
    }

    /** {@inheritDoc} */
    public function get(
        User $user,
        string|BackedEnum $identifier,
        mixed $default = null,
        ?string $locale = null,
    ): mixed {
        $row = $this->query($user)
            ->where('identifier', PreferenceIdentifier::normalize($identifier))
            ->first();

        return $row === null ? $default : $this->resolve($row, $default, $locale);
    }

    /**
     * Merge a locale value or locale map without removing existing translations.
     *
     * @return array<string, mixed>
     */
    private function mergeTranslations(?UserPreference $current, mixed $incoming, ?string $locale): array
    {
        $stored = $current === null
            ? []
            : $this->codec->decode($current->payload, $current->is_encrypted, $current->identifier);
        $translations = is_array($stored) ? $stored : [];

        if ($locale !== null) {
            $translations[$locale] = $incoming;

            return $translations;
        }

        if (is_array($incoming) && ! array_is_list($incoming)) {
            foreach ($incoming as $key => $value) {
                if (! is_string($key)) {
                    throw new InvalidArgumentException('Preference locale must be a string.');
                }

                $this->assertLocale($key);
                $translations[$key] = $value;
            }

            return $translations;
        }

        $activeLocale = app()->getLocale();
        $this->assertLocale($activeLocale);
        $translations[$activeLocale] = $incoming;

        return $translations;
    }

    /** {@inheritDoc} */
    public function forget(User $user, string|BackedEnum $identifier): bool
    {
        return $this->query($user)
            ->where('identifier', PreferenceIdentifier::normalize($identifier))
            ->delete() > 0;
    }
}
