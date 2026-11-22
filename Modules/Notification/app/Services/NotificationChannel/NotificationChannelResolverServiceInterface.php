<?php

namespace Modules\Notification\Services\NotificationChannel;

interface NotificationChannelResolverServiceInterface
{
    /**
     * Get enabled notification channels from the service registry.
     *
     * @return array<int, array<string, mixed>>
     */
    public function all(): array;

    /**
     * Get enabled notification channel keys.
     *
     * @return array<int, string>
     */
    public function keys(): array;

    /**
     * Determine if a notification channel is enabled and available.
     */
    public function exists(string $channel): bool;
}
