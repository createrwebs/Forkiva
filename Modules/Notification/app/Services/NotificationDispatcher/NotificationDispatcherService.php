<?php

namespace Modules\Notification\Services\NotificationDispatcher;

use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Notification;
use Modules\Notification\Enums\NotificationContext;
use Modules\Notification\Models\NotificationTemplate;
use Modules\Notification\Models\NotificationWorkflow;
use Modules\Notification\Notifications\RenderedTemplateNotification;
use Modules\Notification\Services\NotificationChannel\NotificationChannelResolverServiceInterface;
use Modules\Notification\Services\NotificationRecipient\NotificationRecipientRoleResolverServiceInterface;
use Modules\Notification\Services\NotificationTemplate\NotificationTemplateRendererServiceInterface;
use Modules\Notification\Support\GuestNotifiable;
use Modules\Notification\Support\NotificationWorkflowRecipientCatalog;
use Modules\User\Enums\DefaultRole;
use Modules\User\Models\User;

class NotificationDispatcherService implements NotificationDispatcherServiceInterface
{
    /**
     * Create a new NotificationDispatcherService instance.
     */
    public function __construct(
        protected NotificationChannelResolverServiceInterface $channelResolver,
        protected NotificationTemplateRendererServiceInterface $renderer,
        protected NotificationRecipientRoleResolverServiceInterface $recipientResolver,
    ) {}

    /** {@inheritDoc} */
    public function send(string $templateKey, User|GuestNotifiable|array|Collection $notifiables, array $variables = [], ?string $locale = null): void
    {
        $normalizedNotifiables = $this->normalizeNotifiables($notifiables);
        if ($normalizedNotifiables->isEmpty()) {
            return;
        }

        $template = NotificationTemplate::query()
            ->with('messages')
            ->where('key', $templateKey)
            ->first();

        $workflow = NotificationWorkflow::query()
            ->where('key', $templateKey)
            ->first();

        if (! $template || ! $workflow) {
            return;
        }

        $this->loadRecipientData($normalizedNotifiables, $workflow->key);

        $notifiablesByChannels = $this->notifiablesByChannels($normalizedNotifiables, $template, $workflow);
        if ($notifiablesByChannels->isEmpty()) {
            return;
        }

        foreach ($notifiablesByChannels as $channelGroup) {
            $channels = $channelGroup['channels'];
            $requiresPerNotifiableRendering = $this->requiresPerNotifiableRendering($template, $variables);

            if ($requiresPerNotifiableRendering) {
                foreach ($channelGroup['notifiables'] as $notifiable) {
                    $this->sendRenderedNotification(
                        template: $template,
                        channels: $channels,
                        recipientRoles: $channelGroup['recipient_roles'],
                        notifiables: collect([$notifiable]),
                        variables: $this->notificationVariablesFor(
                            variables: $variables,
                            notifiable: $notifiable,
                            appContext: $channelGroup['app_context'] ?? null,
                        ),
                        locale: $locale,
                    );
                }

                continue;
            }

            $this->sendRenderedNotification(
                template: $template,
                channels: $channels,
                recipientRoles: $channelGroup['recipient_roles'],
                notifiables: $channelGroup['notifiables'],
                variables: $this->notificationVariablesFor(
                    variables: $variables,
                    appContext: $channelGroup['app_context'] ?? null,
                ),
                locale: $locale,
            );
        }
    }

    /**
     * Normalize one or more notifiables.
     *
     * @param  User|GuestNotifiable|array<int, User|GuestNotifiable>|Collection<int, User|GuestNotifiable>  $notifiables
     * @return Collection<int, User|GuestNotifiable>
     */
    protected function normalizeNotifiables(User|GuestNotifiable|array|Collection $notifiables): Collection
    {
        if ($notifiables instanceof User || $notifiables instanceof GuestNotifiable) {
            return collect([$notifiables]);
        }

        return collect($notifiables)
            ->filter(fn ($notifiable) => $notifiable instanceof User || $notifiable instanceof GuestNotifiable)
            ->values();
    }

    /**
     * Eager load recipient data before channel filtering.
     *
     * @param  Collection<int, User|GuestNotifiable>  $notifiables
     */
    protected function loadRecipientData(Collection $notifiables, string $workflowKey): void
    {
        $users = $notifiables
            ->filter(fn ($notifiable) => $notifiable instanceof User)
            ->values();

        if ($users->isNotEmpty()) {
            (new EloquentCollection($users->all()))->loadMissing('roles');
        }

        if ($this->workflowNeedsProfileContexts($workflowKey)) {
            $this->recipientResolver->preloadContextsFor($users);
        }
    }

    /**
     * Determine whether workflow dispatch needs profile-derived contexts.
     */
    protected function workflowNeedsProfileContexts(string $workflowKey): bool
    {
        return in_array($workflowKey, NotificationWorkflowRecipientCatalog::driverAppWorkflowKeys(), true)
            || in_array($workflowKey, NotificationWorkflowRecipientCatalog::employeeAppWorkflowKeys(), true)
            || in_array($workflowKey, NotificationWorkflowRecipientCatalog::accountWorkflowKeys(), true);
    }

    /**
     * Group notifiables by final sendable channel set.
     *
     * @param  Collection<int, User|GuestNotifiable>  $notifiables
     * @return Collection<int, array{channels: array<int, string>, recipient_roles: array<string, string>, app_context: string|null, notifiables: Collection<int, User|GuestNotifiable>}>
     */
    protected function notifiablesByChannels(Collection $notifiables, NotificationTemplate $template, NotificationWorkflow $workflow): Collection
    {
        $availableChannels = $this->channelResolver->keys();
        $messageChannels = $template->messages
            ->pluck('channel_key')
            ->unique()
            ->values()
            ->all();
        $workflowChannels = $workflow->channels ?? [];
        $needsProfileContexts = $this->workflowNeedsProfileContexts($workflow->key);
        $groups = collect();

        foreach ($notifiables as $notifiable) {
            if ($notifiable instanceof GuestNotifiable) {
                $notificationContexts = [];
                $channelRoles = $this->sendableChannelRolesForGuestNotifiable(
                    notifiable: $notifiable,
                    workflowKey: $workflow->key,
                    workflowChannels: $workflowChannels,
                    availableChannels: $availableChannels,
                    messageChannels: $messageChannels,
                );
            } else {
                $notificationContexts = $needsProfileContexts
                    ? $this->recipientResolver->contextsFor($notifiable)
                    : [];
                $channelRoles = $this->sendableChannelRolesForNotifiable(
                    notifiable: $notifiable,
                    workflowKey: $workflow->key,
                    workflowChannels: $workflowChannels,
                    availableChannels: $availableChannels,
                    messageChannels: $messageChannels,
                    notificationContexts: $notificationContexts,
                );
            }

            $channels = array_keys($channelRoles);

            if (empty($channels)) {
                continue;
            }

            $appContext = $this->appContextForWorkflow($workflow->key, $channels, $notificationContexts);
            $roleKey = implode('|', array_map(
                fn (string $channel) => "$channel:$channelRoles[$channel]",
                $channels
            ));
            $key = implode('|', $channels).'|'.$roleKey.'|'.($appContext ?: 'external');
            $group = $groups->get($key, [
                'channels' => $channels,
                'recipient_roles' => $channelRoles,
                'app_context' => $appContext,
                'notifiables' => collect(),
            ]);
            $group['notifiables']->push($notifiable);
            $groups->put($key, $group);
        }

        return $groups->values();
    }

    /**
     * Get channels and recipient-role variants allowed for one guest notifiable.
     *
     * @param  array<string, mixed>  $workflowChannels
     * @param  array<int, string>  $availableChannels
     * @param  array<int, string>  $messageChannels
     * @return array<string, string>
     */
    protected function sendableChannelRolesForGuestNotifiable(
        GuestNotifiable $notifiable,
        string $workflowKey,
        array $workflowChannels,
        array $availableChannels,
        array $messageChannels,
    ): array {
        if (! $notifiable->hasContactRoute()) {
            return [];
        }

        $roleKeys = [DefaultRole::Customer->value];
        $channelRoles = [];

        foreach (array_intersect($availableChannels, $messageChannels, array_keys($workflowChannels)) as $channel) {
            if (! $this->guestSupportsChannel($notifiable, $channel)) {
                continue;
            }

            $channelRecipients = $workflowChannels[$channel] ?? [];
            $role = $this->enabledRecipientRoleForChannel(
                roleKeys: $roleKeys,
                workflowKey: $workflowKey,
                channel: $channel,
                channelRecipients: $channelRecipients,
            );

            if ($role !== null) {
                $channelRoles[$channel] = $role;
            }
        }

        ksort($channelRoles);

        return $channelRoles;
    }

    /**
     * Determine whether the guest can receive the requested channel.
     */
    protected function guestSupportsChannel(GuestNotifiable $notifiable, string $channel): bool
    {
        return match ($channel) {
            'email' => filled($notifiable->email),
            'sms', 'whatsapp' => filled($notifiable->phone),
            default => false,
        };
    }

    /**
     * Resolve the recipient role that controls one channel's message variant.
     *
     * @param  array<int, string>  $roleKeys
     */
    protected function enabledRecipientRoleForChannel(
        array $roleKeys,
        string $workflowKey,
        string $channel,
        mixed $channelRecipients,
    ): ?string {
        foreach (NotificationWorkflowRecipientCatalog::allowedRolesFor($workflowKey, $channel) as $role) {
            if (! in_array($role, $roleKeys, true)) {
                continue;
            }

            if ($channelRecipients === true) {
                return $role;
            }

            if (is_array($channelRecipients) && $this->channelRecipientEnabled($channelRecipients, $role)) {
                return $role;
            }
        }

        return null;
    }

    /**
     * Check if a workflow channel enables the recipient key.
     *
     * @param  array<string, mixed>  $channelRecipients
     */
    protected function channelRecipientEnabled(array $channelRecipients, string $role): bool
    {
        return ! empty($channelRecipients[$role]);
    }

    /**
     * Get channels and recipient-role variants allowed for one notifiable.
     *
     * @param  array<string, mixed>  $workflowChannels
     * @param  array<int, string>  $availableChannels
     * @param  array<int, string>  $messageChannels
     * @param  array<int, string>  $notificationContexts
     * @return array<string, string>
     */
    protected function sendableChannelRolesForNotifiable(
        User $notifiable,
        string $workflowKey,
        array $workflowChannels,
        array $availableChannels,
        array $messageChannels,
        array $notificationContexts,
    ): array {
        $roleKeys = $this->effectiveRoleKeysFor(
            roleKeys: $this->recipientResolver->keysFor($notifiable),
            workflowKey: $workflowKey,
            notificationContexts: $notificationContexts,
        );

        if (empty($roleKeys)) {
            return [];
        }

        $channelRoles = [];

        foreach (array_intersect($availableChannels, $messageChannels, array_keys($workflowChannels)) as $channel) {
            $channelRecipients = $workflowChannels[$channel] ?? [];
            $role = $this->enabledRecipientRoleForChannel(
                roleKeys: $roleKeys,
                workflowKey: $workflowKey,
                channel: $channel,
                channelRecipients: $channelRecipients,
            );

            if ($role !== null) {
                $channelRoles[$channel] = $role;
            }
        }

        ksort($channelRoles);

        return $channelRoles;
    }

    /**
     * Resolve role keys that can satisfy workflow settings for this user.
     *
     * Profile assignment is authoritative for driver and employee app targets.
     * Account-created workflows keep role behavior and additionally allow linked
     * employee accounts without requiring the staff role.
     *
     * @param  array<int, string>  $roleKeys
     * @param  array<int, string>  $notificationContexts
     * @return array<int, string>
     */
    protected function effectiveRoleKeysFor(array $roleKeys, string $workflowKey, array $notificationContexts): array
    {
        $keys = $roleKeys;

        if (in_array($workflowKey, NotificationWorkflowRecipientCatalog::driverAppWorkflowKeys(), true)) {
            $keys = array_values(array_diff($keys, [DefaultRole::Driver->value]));

            if (in_array(NotificationContext::Driver->value, $notificationContexts, true)) {
                $keys[] = DefaultRole::Driver->value;
            }
        }

        if (in_array($workflowKey, NotificationWorkflowRecipientCatalog::employeeAppWorkflowKeys(), true)) {
            $keys = array_values(array_diff($keys, [DefaultRole::Staff->value]));

            if (in_array(NotificationContext::Employee->value, $notificationContexts, true)) {
                $keys[] = DefaultRole::Staff->value;
            }
        } elseif (
            in_array($workflowKey, NotificationWorkflowRecipientCatalog::accountWorkflowKeys(), true)
            && in_array(NotificationContext::Employee->value, $notificationContexts, true)
        ) {
            $keys[] = DefaultRole::Staff->value;
        }

        return array_values(array_unique($keys));
    }

    /**
     * Resolve app context for recipient-specific database/push filtering.
     *
     * @param  array<int, string>  $channels
     * @param  array<int, string>  $notificationContexts
     */
    protected function appContextForWorkflow(string $workflowKey, array $channels, array $notificationContexts): ?string
    {
        if (! array_intersect($channels, ['in_app', 'push_notification'])) {
            return null;
        }

        if (
            in_array($workflowKey, NotificationWorkflowRecipientCatalog::driverAppWorkflowKeys(), true)
            && in_array(NotificationContext::Driver->value, $notificationContexts, true)
        ) {
            return NotificationContext::Driver->value;
        }

        if (
            in_array($workflowKey, NotificationWorkflowRecipientCatalog::employeeAppWorkflowKeys(), true)
            && in_array(NotificationContext::Employee->value, $notificationContexts, true)
        ) {
            return NotificationContext::Employee->value;
        }

        return NotificationContext::Other->value;
    }

    /**
     * Determine if the payload must be rendered separately per recipient.
     *
     * Recipient greetings rely on the recipient's own display name.
     *
     * @param  array<string, mixed>  $variables
     */
    protected function requiresPerNotifiableRendering(NotificationTemplate $template, array $variables): bool
    {
        return in_array('user_name', $template->supported_variables ?? [], true)
            && ! array_key_exists('user_name', $variables);
    }

    /**
     * Render and send one notification payload to one or more users.
     *
     * @param  array<int, string>  $channels
     * @param  array<string, string>  $recipientRoles
     * @param  Collection<int, User|GuestNotifiable>  $notifiables
     * @param  array<string, mixed>  $variables
     */
    protected function sendRenderedNotification(
        NotificationTemplate $template,
        array $channels,
        array $recipientRoles,
        Collection $notifiables,
        array $variables,
        ?string $locale = null,
    ): void {
        $renderedChannels = [];

        foreach ($channels as $channel) {
            $renderedChannels[$channel] = $this->renderer->renderTemplate(
                template: $template,
                channel: $channel,
                variables: $variables,
                locale: $locale,
                recipientRoleKey: $recipientRoles[$channel] ?? 'default',
            );
        }

        if (empty($renderedChannels)) {
            return;
        }

        if (in_array('push_notification', $channels, true)) {
            $this->loadPushTokens($notifiables);
        }

        Notification::send(
            $notifiables,
            new RenderedTemplateNotification(
                renderedChannels: $renderedChannels,
                appName: $this->appName(),
            )
        );
    }

    /**
     * Eager load push tokens for push notification routing.
     *
     * @param  Collection<int, User|GuestNotifiable>  $notifiables
     */
    protected function loadPushTokens(Collection $notifiables): void
    {
        $users = $notifiables
            ->filter(fn ($notifiable) => $notifiable instanceof User)
            ->values();

        if ($users->isEmpty()) {
            return;
        }

        (new EloquentCollection($users->all()))->loadMissing('pushTokens');
    }

    /**
     * Get app display name.
     */
    protected function appName(): string
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
     * Merge notification variables with recipient-specific fallbacks.
     *
     * @param  array<string, mixed>  $variables
     * @return array<string, mixed>
     */
    protected function notificationVariablesFor(array $variables, User|GuestNotifiable|null $notifiable = null, ?string $appContext = null): array
    {
        $resolved = $variables;

        if ($notifiable instanceof User && ! array_key_exists('user_name', $resolved)) {
            $resolved['user_name'] = $notifiable->name ?: '-';
        }

        if ($notifiable instanceof GuestNotifiable && ! array_key_exists('user_name', $resolved)) {
            $resolved['user_name'] = $notifiable->name ?: '-';
        }

        if (! blank($appContext)) {
            $resolved['app_context'] = $appContext;
        }

        return $resolved;
    }
}
