<?php

namespace Modules\Notification\Services\NotificationTemplate;

use Modules\Notification\Models\NotificationTemplate;

interface NotificationTemplateRendererServiceInterface
{
    /**
     * Render a template for a channel and locale.
     *
     * @param  array<string, mixed>  $variables
     * @return array<string, mixed>
     */
    public function render(
        string $templateKey,
        string $channel,
        array $variables = [],
        ?string $locale = null,
        ?string $recipientRoleKey = null,
    ): array;

    /**
     * Render an already-loaded template for a channel and locale.
     *
     * @param  array<string, mixed>  $variables
     * @return array<string, mixed>
     */
    public function renderTemplate(
        NotificationTemplate $template,
        string $channel,
        array $variables = [],
        ?string $locale = null,
        ?string $recipientRoleKey = null,
    ): array;
}
