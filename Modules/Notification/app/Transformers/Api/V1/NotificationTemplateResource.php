<?php

namespace Modules\Notification\Transformers\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Modules\Notification\Models\NotificationTemplate;
use Modules\Notification\Services\NotificationChannel\NotificationChannelResolverServiceInterface;

/** @mixin NotificationTemplate */
class NotificationTemplateResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'key' => $this->key,
            'name' => $this->name,
            'description' => $this->description,
            'channels' => $this->availableChannels(),
            'created_at' => dateTimeFormat($this->created_at),
            'updated_at' => dateTimeFormat($this->updated_at),
        ];
    }

    /**
     * Get currently enabled channels that this template has message rows for.
     *
     * @return array<int, array<string, mixed>>
     */
    protected function availableChannels(): array
    {
        $messageChannelKeys = $this->messageChannelKeys();

        return array_values(array_filter(
            app(NotificationChannelResolverServiceInterface::class)->all(),
            fn (array $channel) => in_array((string) $channel['key'], $messageChannelKeys, true)
        ));
    }

    /**
     * Get template message channel keys.
     *
     * @return array<int, string>
     */
    protected function messageChannelKeys(): array
    {
        if ($this->relationLoaded('messages')) {
            return $this->messages
                ->pluck('channel_key')
                ->unique()
                ->values()
                ->all();
        }

        return $this->messages()
            ->pluck('channel_key')
            ->unique()
            ->values()
            ->all();
    }
}
