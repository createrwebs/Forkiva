<?php

namespace Modules\Notification\Services\NotificationChannel;

use Illuminate\Support\Arr;
use Modules\Service\Services\ServiceRegistry\ServiceRegistryServiceInterface;

class NotificationChannelResolverService implements NotificationChannelResolverServiceInterface
{
    public const CATEGORY = 'notifications';

    public const EMAIL_CHANNEL = 'email';

    /**
     * Cached enabled notification services for this resolver instance.
     *
     * @var array<int, array<string, mixed>>|null
     */
    protected ?array $channels = null;

    /**
     * Create a new NotificationChannelResolverService instance.
     */
    public function __construct(protected ServiceRegistryServiceInterface $serviceRegistry) {}

    /** {@inheritDoc} */
    public function all(): array
    {
        if ($this->channels !== null) {
            return $this->channels;
        }

        $category = collect($this->serviceRegistry->all())
            ->firstWhere('key', self::CATEGORY);

        return $this->channels = array_values(array_filter(
            $category['services'] ?? [],
            fn (array $channel) => $this->isEnabled($channel)
        ));
    }

    /** {@inheritDoc} */
    public function keys(): array
    {
        return array_values(array_map(
            fn (array $channel) => (string) $channel['key'],
            $this->all()
        ));
    }

    /** {@inheritDoc} */
    public function exists(string $channel): bool
    {
        return in_array($channel, $this->keys(), true);
    }

    /**
     * Determine if the notification service is enabled in service settings.
     *
     * @param  array<string, mixed>  $channel
     */
    protected function isEnabled(array $channel): bool
    {
        if (! Arr::has($channel, 'values.enabled')) {
            return false;
        }

        return filter_var(Arr::get($channel, 'values.enabled'), FILTER_VALIDATE_BOOLEAN);
    }
}
