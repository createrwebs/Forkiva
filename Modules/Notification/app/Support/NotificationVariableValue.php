<?php

namespace Modules\Notification\Support;

use Illuminate\Support\Arr;

class NotificationVariableValue
{
    /**
     * Get the configured app display name.
     */
    public static function appName(): string
    {
        $appName = setting('app_name', config('app.name'));

        if (is_array($appName)) {
            return (string) (
                $appName[app()->getLocale()]
                ?? $appName[setting('default_locale', 'en')]
                ?? Arr::first($appName)
                ?? config('app.name')
            );
        }

        return (string) $appName;
    }

    /**
     * Format changed field names for notification copy.
     *
     * @param  array<int, string>  $fields
     */
    public static function changeType(array $fields): string
    {
        return collect($fields)
            ->map(fn (string $field) => str_replace('_', ' ', $field))
            ->implode(', ');
    }

    /**
     * Format a parsed user agent as a readable device name.
     *
     * @param  array<string, mixed>  $agent
     */
    public static function deviceName(array $agent): string
    {
        $deviceName = trim(collect([
            $agent['platform'] ?? null,
            $agent['browser'] ?? null,
        ])->filter()->implode(' '));

        return $deviceName ?: __('notification::notifications.unknown_device');
    }
}
