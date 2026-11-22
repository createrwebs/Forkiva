<?php

namespace Modules\UserPreference\Services\UserPreference;

use BackedEnum;
use Modules\User\Models\User;
use Throwable;

interface UserPreferenceServiceInterface
{
    /**
     * Return a user's resolved preferences, optionally limited by identifier prefix.
     *
     * @return array<string, mixed>
     */
    public function all(User $user, ?string $locale = null, ?string $prefix = null): array;

    /**
     * Return a resolved preference or its default when the identifier is absent.
     */
    public function get(
        User $user,
        string|BackedEnum $identifier,
        mixed $default = null,
        ?string $locale = null,
    ): mixed;

    /**
     * Store one value while retaining existing encryption and translation flags.
     *
     * @throws Throwable
     */
    public function set(
        User $user,
        string|BackedEnum $identifier,
        mixed $value,
        bool $encrypted = false,
        bool $translatable = false,
        ?string $locale = null,
    ): void;

    /**
     * Store a preference map atomically while retaining existing protection flags.
     *
     * @param  array<string, mixed>  $preferences
     *
     * @throws Throwable
     */
    public function setMany(
        User $user,
        array $preferences,
        bool $encrypted = false,
        bool $translatable = false,
        ?string $locale = null,
    ): void;

    /**
     * Remove one of the user's preferences if it exists.
     */
    public function forget(User $user, string|BackedEnum $identifier): bool;
}
