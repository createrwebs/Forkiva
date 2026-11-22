<?php

namespace Modules\Notification\Transformers\Api\V1;

use Illuminate\Http\Request;
use Modules\Notification\Enums\NotificationTemplateContentType;
use Modules\Notification\Models\NotificationTemplate;
use Modules\Notification\Models\NotificationTemplateMessage;
use Modules\Notification\Services\NotificationRecipient\NotificationRecipientRoleResolverServiceInterface;
use Modules\Notification\Support\NotificationWorkflowRecipientCatalog;

/** @mixin NotificationTemplate */
class NotificationTemplateShowResource extends NotificationTemplateResource
{
    public function toArray(Request $request): array
    {
        return [
            ...(new NotificationTemplateResource($this))->toArray($request),
            'supported_variables' => $this->variables(),
            'supported_variables_text' => implode(', ', array_column($this->variables(), 'token')),
            'content_types_text' => $this->contentTypesText(),
            'available_recipients' => $this->availableRecipients(),
            'recipient_support' => $this->recipientSupport(),
            'messages' => $this->messagesByChannel(),
        ];
    }

    /**
     * Get supported variables formatted for the admin form.
     *
     * @return array<int, array<string, string>>
     */
    protected function variables(): array
    {
        return array_values(array_map(
            fn (string $variable) => [
                'key' => $variable,
                'token' => "{{$variable}}",
            ],
            $this->supported_variables ?? []
        ));
    }

    /**
     * Get content types formatted for the table.
     */
    protected function contentTypesText(): string
    {
        if (! $this->relationLoaded('messages')) {
            return '';
        }

        $types = [];

        foreach ($this->messages as $message) {
            if ($message->content_type) {
                $types[$message->content_type->value] = $message->content_type->trans();
            }
        }

        return implode(', ', $types);
    }

    /**
     * Group messages by channel key.
     *
     * @return array<string, array<string, array<string, mixed>>>
     */
    protected function messagesByChannel(): array
    {
        if (! $this->relationLoaded('messages')) {
            return [];
        }

        $messages = [];

        foreach ($this->messages as $message) {
            $contentType = $message->content_type instanceof NotificationTemplateContentType
                ? $message->content_type->value
                : $message->content_type;

            $role = $message->recipient_role_key ?: NotificationTemplateMessage::DEFAULT_RECIPIENT_ROLE;

            $messages[$message->channel_key][$role] = [
                'title' => $message->getTranslations('title'),
                'subject' => $message->getTranslations('subject'),
                'content' => $message->getTranslations('content'),
                'content_type' => $contentType,
            ];
        }

        return $messages;
    }

    /**
     * Get recipient roles supported by this template.
     *
     * @return array<int, array<string, mixed>>
     */
    protected function availableRecipients(): array
    {
        $roles = app(NotificationRecipientRoleResolverServiceInterface::class)->all();
        $allowedRoles = [];

        foreach ($this->messageChannelKeys() as $channelKey) {
            $allowedRoles = [
                ...$allowedRoles,
                ...NotificationWorkflowRecipientCatalog::allowedRolesFor($this->key, $channelKey),
            ];
        }

        $allowedRoles = array_values(array_unique($allowedRoles));

        return array_values(array_filter(
            $roles,
            fn (array $role) => in_array((string) $role['key'], $allowedRoles, true)
        ));
    }

    /**
     * Get recipient support keyed by channel and role key.
     *
     * @return array<string, array<string, bool>>
     */
    protected function recipientSupport(): array
    {
        $support = [];

        foreach ($this->messageChannelKeys() as $channelKey) {
            foreach ($this->availableRecipients() as $recipient) {
                $role = (string) $recipient['key'];
                $support[$channelKey][$role] = in_array(
                    $role,
                    NotificationWorkflowRecipientCatalog::allowedRolesFor($this->key, $channelKey),
                    true
                );
            }
        }

        return $support;
    }
}
