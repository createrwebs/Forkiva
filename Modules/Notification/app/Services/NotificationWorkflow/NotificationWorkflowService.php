<?php

namespace Modules\Notification\Services\NotificationWorkflow;

use App\Forkiva;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Validation\ValidationException;
use Modules\Notification\Models\NotificationTemplate;
use Modules\Notification\Models\NotificationWorkflow;
use Modules\Notification\Services\NotificationChannel\NotificationChannelResolverServiceInterface;
use Modules\Notification\Services\NotificationRecipient\NotificationRecipientRoleResolverServiceInterface;
use Modules\Notification\Support\NotificationWorkflowRecipientCatalog;

class NotificationWorkflowService implements NotificationWorkflowServiceInterface
{
    /**
     * Create a new NotificationWorkflowService instance.
     */
    public function __construct(
        protected NotificationChannelResolverServiceInterface $channelResolver,
        protected NotificationRecipientRoleResolverServiceInterface $recipientResolver,
    ) {}

    /** {@inheritDoc} */
    public function get(array $filters = [], ?array $sorts = []): LengthAwarePaginator
    {
        return NotificationWorkflow::query()
            ->sortBy($sorts ?? [])
            ->filters($filters)
            ->paginate(Forkiva::paginate())
            ->withQueryString();
    }

    /** {@inheritDoc} */
    public function update(string $key, array $channels): NotificationWorkflow
    {
        $workflow = $this->show($key);
        $channels = $this->validatedChannels($workflow, $channels);

        if (! $this->hasEnabledRecipient($channels)) {
            throw ValidationException::withMessages([
                'channels' => __('notification::notification_workflows.errors.at_least_one_recipient'),
            ]);
        }

        $workflow->update([
            'channels' => $channels,
        ]);

        return $workflow->refresh();
    }

    /** {@inheritDoc} */
    public function show(string $key): NotificationWorkflow
    {
        return NotificationWorkflow::query()
            ->where('key', $key)
            ->firstOrFail();
    }

    /**
     * Validate and normalize submitted recipient matrix.
     *
     * @param  array<string, mixed>  $channels
     * @return array<string, array<string, bool>>
     *
     * @throws ValidationException
     */
    protected function validatedChannels(NotificationWorkflow $workflow, array $channels): array
    {
        $supportedChannels = $this->supportedChannelKeys($workflow);
        $availableRoles = $this->recipientResolver->keys();
        $normalized = [];

        foreach ($supportedChannels as $channel) {
            $normalized[$channel] = array_fill_keys(
                $this->allowedRoleKeys($workflow->key, $channel, $availableRoles),
                false
            );
        }

        foreach ($channels as $channel => $roles) {
            if (! $this->channelResolver->exists($channel) || ! in_array($channel, $supportedChannels, true)) {
                throw ValidationException::withMessages([
                    "channels.$channel" => __('notification::notification_workflows.errors.unsupported_channel'),
                ]);
            }

            if (! is_array($roles)) {
                $roles = [];
            }

            $allowedRoles = $this->allowedRoleKeys($workflow->key, $channel, $availableRoles);
            $normalized[$channel] = array_fill_keys($allowedRoles, false);

            foreach ($roles as $role => $enabled) {
                $role = (string) $role;

                if (! in_array($role, $availableRoles, true) || ! in_array($role, $allowedRoles, true)) {
                    throw ValidationException::withMessages([
                        "channels.$channel.$role" => __('notification::notification_workflows.errors.unsupported_recipient'),
                    ]);
                }

                $normalized[$channel][$role] = (bool) $enabled;
            }
        }

        return $normalized;
    }

    /**
     * Get template-supported channel keys for the workflow.
     *
     * @return array<int, string>
     */
    protected function supportedChannelKeys(NotificationWorkflow $workflow): array
    {
        $template = NotificationTemplate::query()
            ->where('key', $workflow->key)
            ->first();

        if (! $template) {
            return [];
        }

        return array_values(array_intersect(
            $template->messages()
                ->pluck('channel_key')
                ->unique()
                ->values()
                ->all(),
            $this->channelResolver->keys()
        ));
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
     * Determine if any role/channel pair is enabled.
     *
     * @param  array<string, array<string, bool>>  $channels
     */
    protected function hasEnabledRecipient(array $channels): bool
    {
        foreach ($channels as $roles) {
            foreach ($roles as $enabled) {
                if ($enabled) {
                    return true;
                }
            }
        }

        return false;
    }
}
