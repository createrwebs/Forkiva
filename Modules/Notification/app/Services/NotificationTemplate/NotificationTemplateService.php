<?php

namespace Modules\Notification\Services\NotificationTemplate;

use App\Forkiva;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Arr;
use Modules\Notification\Enums\NotificationTemplateContentType;
use Modules\Notification\Models\NotificationTemplate;
use Modules\Notification\Models\NotificationTemplateMessage;
use Modules\Notification\Services\NotificationChannel\NotificationChannelResolverService;
use Modules\Notification\Support\NotificationContentSanitizer;

class NotificationTemplateService implements NotificationTemplateServiceInterface
{
    /**
     * Create a new notification template service.
     */
    public function __construct(protected NotificationContentSanitizer $contentSanitizer) {}

    /** {@inheritDoc} */
    public function get(array $filters = [], ?array $sorts = []): LengthAwarePaginator
    {
        return NotificationTemplate::query()
            ->with('messages:id,template_key,channel_key')
            ->sortBy($sorts)
            ->filters($filters)
            ->paginate(Forkiva::paginate())
            ->withQueryString();
    }

    /** {@inheritDoc} */
    public function update(string $key, array $data): NotificationTemplate
    {
        $template = $this->show($key);

        $template->update(Arr::only($data, ['name', 'description']));

        foreach (($data['messages'] ?? []) as $channel => $message) {
            $channel = (string) $channel;
            $isEmail = $channel === NotificationChannelResolverService::EMAIL_CHANNEL;

            foreach ($this->messageVariants(is_array($message) ? $message : []) as $role => $variant) {
                NotificationTemplateMessage::query()
                    ->updateOrCreate(
                        [
                            'template_key' => $template->key,
                            'channel_key' => $channel,
                            'recipient_role_key' => $role,
                        ],
                        [
                            'title' => $this->contentSanitizer->translations(
                                $variant['title'] ?? null,
                                false,
                            ),
                            'subject' => $isEmail
                                ? $this->contentSanitizer->translations($variant['subject'] ?? null, false)
                                : null,
                            'content' => $this->contentSanitizer->translations(
                                $variant['content'] ?? null,
                                $isEmail,
                            ),
                            'content_type' => $isEmail
                                ? NotificationTemplateContentType::Html->value
                                : NotificationTemplateContentType::Text->value,
                        ]
                    );
            }
        }

        return $template->refresh();
    }

    /** {@inheritDoc} */
    public function show(string $key): NotificationTemplate
    {
        return NotificationTemplate::query()
            ->with('messages')
            ->where('key', $key)
            ->firstOrFail();
    }

    /**
     * Normalize legacy channel messages and role-keyed message variants.
     *
     * @param  array<string, mixed>  $message
     * @return array<string, array<string, mixed>>
     */
    protected function messageVariants(array $message): array
    {
        if (array_key_exists('content', $message)
            || array_key_exists('subject', $message)
            || array_key_exists('title', $message)) {
            return [NotificationTemplateMessage::DEFAULT_RECIPIENT_ROLE => $message];
        }

        return array_filter($message, function ($variant) {
            return is_array($variant);
        });
    }
}
