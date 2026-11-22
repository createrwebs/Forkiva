<?php

namespace Modules\Notification\Database\Seeders;

use Illuminate\Database\Seeder;
use Modules\Notification\Database\Seeders\NotificationTemplates\NotificationRequirementTemplateCatalog;
use Modules\Notification\Models\NotificationWorkflow;
use Modules\Notification\Services\NotificationChannel\NotificationChannelResolverServiceInterface;
use Modules\Notification\Services\NotificationRecipient\NotificationRecipientRoleResolverServiceInterface;
use Modules\Notification\Support\NotificationWorkflowRecipientCatalog;

class NotificationWorkflowDatabaseSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        foreach ($this->workflows() as $index => $workflow) {
            $workflow['order'] = $index + 1;
            $this->seedWorkflow($workflow);
        }
    }

    /**
     * Get seeded workflow definitions.
     *
     * @return array<int, array<string, mixed>>
     */
    protected function workflows(): array
    {
        return array_values(array_map(
            fn (array $template) => [
                'key' => $template['key'],
                'name' => $template['name'],
                'description' => $template['description'],
                'channel_keys' => NotificationRequirementTemplateCatalog::messageChannelKeys($template),
            ],
            NotificationRequirementTemplateCatalog::all()
        ));
    }

    /**
     * Persist one workflow and merge missing dynamic channels.
     *
     * @param  array<string, mixed>  $workflow
     */
    protected function seedWorkflow(array $workflow): void
    {
        $channelKeys = $workflow['channel_keys'];
        $availableChannelKeys = $this->availableChannelKeys($channelKeys);
        $availableRoleKeys = $this->availableRoleKeys();
        $channelDefaults = $this->channelDefaults(
            workflowKey: $workflow['key'],
            channelKeys: $availableChannelKeys,
            roleKeys: $availableRoleKeys,
        );
        unset($workflow['channel_keys']);

        $model = NotificationWorkflow::query()
            ->where('key', $workflow['key'])
            ->first();

        if ($model) {
            $model->update([
                'name' => $workflow['name'],
                'description' => $workflow['description'],
                'channels' => $this->isUntouchedSeededWorkflow($model)
                    ? $channelDefaults
                    : $this->mergeExistingChannels(
                        workflowKey: $workflow['key'],
                        existingChannels: $model->channels ?? [],
                        defaultChannels: $channelDefaults,
                        channelKeys: $availableChannelKeys,
                        roleKeys: $availableRoleKeys,
                    ),
                'order' => $workflow['order'],
            ]);

            return;
        }

        NotificationWorkflow::query()->create([
            ...$workflow,
            'channels' => $channelDefaults,
        ]);
    }

    /**
     * Get currently available template-supported notification channel keys.
     *
     * @param  array<int, string>  $channelKeys
     * @return array<int, string>
     */
    protected function availableChannelKeys(array $channelKeys): array
    {
        return array_values(array_intersect(
            $channelKeys,
            app(NotificationChannelResolverServiceInterface::class)->keys()
        ));
    }

    /**
     * Get currently available recipient role keys.
     *
     * @return array<int, string>
     */
    protected function availableRoleKeys(): array
    {
        return app(NotificationRecipientRoleResolverServiceInterface::class)->keys();
    }

    /**
     * Get default recipient role matrix for currently available notification channels.
     *
     * @param  array<int, string>  $channelKeys
     * @param  array<int, string>  $roleKeys
     * @return array<string, array<string, bool>>
     */
    protected function channelDefaults(string $workflowKey, array $channelKeys, array $roleKeys): array
    {
        $channels = [];

        foreach ($channelKeys as $channel) {
            $allowedRoles = $this->allowedRoleKeys($workflowKey, $channel, $roleKeys);
            $channels[$channel] = array_fill_keys($allowedRoles, false);

            foreach (NotificationWorkflowRecipientCatalog::defaultRolesFor($workflowKey, $channel) as $role) {
                if (! in_array($role, $allowedRoles, true)) {
                    continue;
                }

                $channels[$channel][$role] = true;
            }
        }

        return $channels;
    }

    /**
     * Merge existing workflow channels without overwriting admin-edited values.
     *
     * @param  array<string, mixed>  $existingChannels
     * @param  array<string, array<string, bool>>  $defaultChannels
     * @param  array<int, string>  $channelKeys
     * @param  array<int, string>  $roleKeys
     * @return array<string, array<string, bool>>
     */
    protected function mergeExistingChannels(
        string $workflowKey,
        array $existingChannels,
        array $defaultChannels,
        array $channelKeys,
        array $roleKeys,
    ): array {
        $channels = [];

        foreach ($channelKeys as $channel) {
            $existing = $existingChannels[$channel] ?? null;
            $allowedRoles = $this->allowedRoleKeys($workflowKey, $channel, $roleKeys);
            $channels[$channel] = array_fill_keys($allowedRoles, false);

            if (is_bool($existing)) {
                $channels[$channel] = $existing
                    ? ($defaultChannels[$channel] ?? $channels[$channel])
                    : $channels[$channel];

                continue;
            }

            if (! is_array($existing)) {
                continue;
            }

            foreach ($allowedRoles as $role) {
                $channels[$channel][$role] = (bool) ($existing[$role] ?? false);
            }
        }

        return $channels;
    }

    /**
     * Get allowed existing role keys for a workflow channel.
     *
     * @param  array<int, string>  $roleKeys
     * @return array<int, string>
     */
    protected function allowedRoleKeys(string $workflowKey, string $channel, array $roleKeys): array
    {
        return array_values(array_intersect(
            $roleKeys,
            NotificationWorkflowRecipientCatalog::allowedRolesFor($workflowKey, $channel)
        ));
    }

    /**
     * Determine whether a workflow still looks like untouched seeded data.
     */
    protected function isUntouchedSeededWorkflow(NotificationWorkflow $workflow): bool
    {
        if (! $workflow->created_at || ! $workflow->updated_at) {
            return false;
        }

        return $workflow->created_at->equalTo($workflow->updated_at);
    }
}
