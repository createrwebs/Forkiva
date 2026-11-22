<?php

namespace Modules\Notification\Transformers\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Modules\Notification\Models\NotificationTemplate;
use Modules\Notification\Models\NotificationWorkflow;
use Modules\Notification\Services\NotificationChannel\NotificationChannelResolverServiceInterface;
use Modules\Notification\Services\NotificationRecipient\NotificationRecipientRoleResolverServiceInterface;
use Modules\Notification\Support\NotificationWorkflowRecipientCatalog;

/** @mixin NotificationWorkflow */
class NotificationWorkflowShowResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $channels = $this->availableChannels();
        $recipients = $this->availableRecipients($channels);
        $channelStates = $this->channelStates($channels, $recipients);

        return [
            ...(new NotificationWorkflowResource($this))->toArray($request),
            'description' => $this->description,
            'channels' => $channels,
            'available_recipients' => $recipients,
            'channel_states' => $channelStates,
            'channel_support' => $this->channelSupport($channels),
            'recipient_support' => $this->recipientSupport($channels, $recipients),
            'order' => $this->order,
            'created_at' => dateTimeFormat($this->created_at),
        ];
    }

    /**
     * Get current dynamic channels with workflow recipient state.
     *
     * @return array<int, array<string, mixed>>
     */
    protected function availableChannels(): array
    {
        $states = $this->channels ?? [];
        $supportedChannelKeys = $this->supportedChannelKeys();

        return array_values(array_filter(array_map(function (array $channel) use ($states, $supportedChannelKeys) {
            $key = (string) $channel['key'];
            $isSupported = in_array($key, $supportedChannelKeys, true);
            $allowedRoles = NotificationWorkflowRecipientCatalog::allowedRolesFor($this->key, $key);
            $roleStates = is_array($states[$key] ?? null)
                ? $states[$key]
                : [];
            $enabledRecipients = $isSupported
                ? array_values(array_intersect(array_keys(array_filter($roleStates)), $allowedRoles))
                : [];

            return [
                ...$channel,
                'enabled_recipients' => $enabledRecipients,
                'is_enabled' => ! empty($enabledRecipients),
                'is_supported' => $isSupported,
            ];
        }, app(NotificationChannelResolverServiceInterface::class)->all()), fn (array $channel) => (bool) $channel['is_supported']));
    }

    /**
     * Get template-supported channel keys for this workflow.
     *
     * @return array<int, string>
     */
    protected function supportedChannelKeys(): array
    {
        return NotificationTemplate::query()
            ->where('key', $this->key)
            ->first()
            ?->messages()
            ->pluck('channel_key')
            ->unique()
            ->values()
            ->all() ?? [];
    }

    /**
     * Get current recipient roles.
     *
     * @param  array<int, array<string, mixed>>  $channels
     * @return array<int, array<string, mixed>>
     */
    protected function availableRecipients(array $channels): array
    {
        $roles = app(NotificationRecipientRoleResolverServiceInterface::class)->all();
        $allowedRoles = [];

        foreach ($channels as $channel) {
            $allowedRoles = [
                ...$allowedRoles,
                ...NotificationWorkflowRecipientCatalog::allowedRolesFor($this->key, (string) $channel['key']),
            ];
        }

        $allowedRoles = array_values(array_unique($allowedRoles));

        return array_values(array_filter(
            $roles,
            fn (array $role) => in_array((string) $role['key'], $allowedRoles, true)
        ));
    }

    /**
     * Get channel states keyed by channel and role key.
     *
     * @param  array<int, array<string, mixed>>  $channels
     * @param  array<int, array<string, mixed>>  $recipients
     * @return array<string, array<string, bool>>
     */
    protected function channelStates(array $channels, array $recipients): array
    {
        $workflowChannels = $this->channels ?? [];
        $states = [];

        foreach ($channels as $channel) {
            $key = (string) $channel['key'];
            $states[$key] = [];
            $roleStates = is_array($workflowChannels[$key] ?? null)
                ? $workflowChannels[$key]
                : [];
            $allowedRoles = NotificationWorkflowRecipientCatalog::allowedRolesFor($this->key, $key);

            foreach ($recipients as $recipient) {
                $role = (string) $recipient['key'];
                if (! in_array($role, $allowedRoles, true)) {
                    continue;
                }

                $states[$key][$role] = (bool) ($roleStates[$role] ?? false);
            }
        }

        return $states;
    }

    /**
     * Get channel support keyed by channel key.
     *
     * @param  array<int, array<string, mixed>>  $channels
     * @return array<string, bool>
     */
    protected function channelSupport(array $channels): array
    {
        $states = [];

        foreach ($channels as $channel) {
            $states[(string) $channel['key']] = (bool) $channel['is_supported'];
        }

        return $states;
    }

    /**
     * Get recipient support keyed by channel and role key.
     *
     * @param  array<int, array<string, mixed>>  $channels
     * @param  array<int, array<string, mixed>>  $recipients
     * @return array<string, array<string, bool>>
     */
    protected function recipientSupport(array $channels, array $recipients): array
    {
        $support = [];

        foreach ($channels as $channel) {
            $channelKey = (string) $channel['key'];
            $allowedRoles = NotificationWorkflowRecipientCatalog::allowedRolesFor($this->key, $channelKey);
            $support[$channelKey] = [];

            foreach ($recipients as $recipient) {
                $role = (string) $recipient['key'];
                $support[$channelKey][$role] = in_array($role, $allowedRoles, true);
            }
        }

        return $support;
    }
}
