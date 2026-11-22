<?php

namespace Modules\Notification\Services\NotificationTemplate;

use InvalidArgumentException;
use Modules\Notification\Models\NotificationTemplate;
use Modules\Notification\Models\NotificationTemplateMessage;
use Modules\Notification\Services\NotificationChannel\NotificationChannelResolverService;
use Modules\Notification\Services\NotificationChannel\NotificationChannelResolverServiceInterface;
use Modules\Notification\Support\NotificationContentSanitizer;

class NotificationTemplateRendererService implements NotificationTemplateRendererServiceInterface
{
    private const VARIABLE_PATTERN = '/\{\{\s*([A-Za-z_][A-Za-z0-9_]*)\s*\}\}|\{([A-Za-z_][A-Za-z0-9_]*)\}/';

    /**
     * Create a new NotificationTemplateRendererService instance.
     */
    public function __construct(
        protected NotificationChannelResolverServiceInterface $channelResolver,
        protected NotificationContentSanitizer $contentSanitizer,
    ) {}

    /** {@inheritDoc} */
    public function render(
        string $templateKey,
        string $channel,
        array $variables = [],
        ?string $locale = null,
        ?string $recipientRoleKey = null,
    ): array {
        $template = NotificationTemplate::query()
            ->with('messages')
            ->where('key', $templateKey)
            ->first();

        if (! $template) {
            throw new InvalidArgumentException(__('notification::notification_templates.errors.template_not_found'));
        }

        return $this->renderTemplate($template, $channel, $variables, $locale, $recipientRoleKey);
    }

    /** {@inheritDoc} */
    public function renderTemplate(
        NotificationTemplate $template,
        string $channel,
        array $variables = [],
        ?string $locale = null,
        ?string $recipientRoleKey = null,
    ): array {
        $locale = $locale ?: app()->getLocale();

        if (! $this->channelResolver->exists($channel)) {
            throw new InvalidArgumentException(__('notification::notification_templates.errors.unsupported_channel'));
        }

        $message = $this->resolveMessage($template, $channel, $recipientRoleKey);
        if (! $message) {
            throw new InvalidArgumentException(__('notification::notification_templates.errors.message_not_found'));
        }

        $allowedVariables = $template->supported_variables ?? [];
        $isEmail = $message->channel_key === NotificationChannelResolverService::EMAIL_CHANNEL;
        $titleTranslations = $this->renderTranslations(
            $message->getTranslations('title') ?: $template->getTranslations('name'),
            $variables,
            $allowedVariables,
        );
        $subjectTranslations = $isEmail
            ? $this->renderTranslations($message->getTranslations('subject'), $variables, $allowedVariables)
            : [];
        $contentTranslations = $this->renderTranslations(
            $message->getTranslations('content'),
            $variables,
            $allowedVariables,
            $isEmail,
        );
        $translations = $this->notificationTranslations($titleTranslations, $subjectTranslations, $contentTranslations);

        [$title] = $this->localizedValue($titleTranslations, $locale);
        [$content] = $this->localizedValue($contentTranslations, $locale);
        if ($content === null) {
            throw new InvalidArgumentException(__('notification::notification_templates.errors.message_not_found'));
        }

        $subject = $isEmail
            ? $this->localizedValue($subjectTranslations, $locale)[0]
            : null;

        $rendered = [
            'type' => (string) ($variables['type'] ?? $template->key),
            'type_id' => $variables['type_id'] ?? null,
            'title' => $title,
            'subject' => $subject,
            'content' => $content,
            'translations' => $translations,
        ];

        if (! blank($variables['app_context'] ?? null)) {
            $rendered['app_context'] = (string) $variables['app_context'];
        }

        return $rendered;
    }

    /**
     * Resolve a channel message.
     */
    protected function resolveMessage(NotificationTemplate $template, string $channel, ?string $recipientRoleKey = null): ?NotificationTemplateMessage
    {
        $recipientRoleKey = blank($recipientRoleKey) ? 'default' : (string) $recipientRoleKey;

        if ($template->relationLoaded('messages')) {
            return $template->messages
                ->first(fn (NotificationTemplateMessage $message) => $message->channel_key === $channel
                    && $message->recipient_role_key === $recipientRoleKey)
                ?: $template->messages
                    ->first(fn (NotificationTemplateMessage $message) => $message->channel_key === $channel
                        && $message->recipient_role_key === 'default');
        }

        return $template->messages()
            ->where('channel_key', $channel)
            ->where('recipient_role_key', $recipientRoleKey)
            ->first()
            ?: $template->messages()
                ->where('channel_key', $channel)
                ->where('recipient_role_key', 'default')
                ->first();
    }

    /**
     * Render translated message values.
     *
     * @param  array<string, string>  $translations
     * @param  array<string, mixed>  $variables
     * @param  array<int, string>  $allowedVariables
     * @return array<string, string>
     */
    protected function renderTranslations(
        array $translations,
        array $variables,
        array $allowedVariables,
        bool $html = false,
    ): array {
        $renderedTranslations = [];

        foreach ($translations as $locale => $value) {
            if (blank($value)) {
                continue;
            }

            $template = $html
                ? $this->contentSanitizer->html((string) $value)
                : $this->contentSanitizer->plain((string) $value);
            $rendered = (string) $this->renderValue(
                $template,
                $variables,
                $allowedVariables,
                $locale,
                $html,
            );
            $renderedTranslations[$locale] = $html
                ? $rendered
                : $this->contentSanitizer->plain($rendered);
        }

        return $renderedTranslations;
    }

    /**
     * Render one message field.
     *
     * @param  array<string, mixed>  $variables
     * @param  array<int, string>  $allowedVariables
     */
    protected function renderValue(
        ?string $value,
        array $variables,
        array $allowedVariables,
        ?string $locale = null,
        bool $escapeHtml = false,
    ): ?string {
        if ($value === null) {
            return null;
        }

        return preg_replace_callback(
            self::VARIABLE_PATTERN,
            function (array $matches) use ($variables, $allowedVariables, $locale, $escapeHtml): string {
                $variable = (string) ($matches[1] ?: $matches[2] ?: '');

                if ($variable === '' || ! in_array($variable, $allowedVariables, true)) {
                    return $matches[0];
                }

                if (! array_key_exists($variable, $variables)) {
                    return $matches[0];
                }

                return $this->renderVariable($variables[$variable], $locale, $escapeHtml);
            },
            $value
        ) ?? $value;
    }

    /**
     * Render a variable value for the active translation locale.
     */
    protected function renderVariable(mixed $variable, ?string $locale, bool $escapeHtml): string
    {
        $rendered = '';

        if (! is_array($variable)) {
            $rendered = (string) $variable;
        } else {
            foreach ($this->localeFallbacks($locale ?: app()->getLocale()) as $fallbackLocale) {
                if (! blank($variable[$fallbackLocale] ?? null)) {
                    $rendered = (string) $variable[$fallbackLocale];
                    break;
                }
            }

            if ($rendered === '') {
                foreach ($variable as $value) {
                    if (! blank($value)) {
                        $rendered = (string) $value;
                        break;
                    }
                }
            }
        }

        return $escapeHtml
            ? $this->contentSanitizer->escapeHtmlVariable($rendered)
            : $rendered;
    }

    /**
     * Get locale fallback order.
     *
     * @return array<int, string>
     */
    protected function localeFallbacks(string $locale): array
    {
        return array_values(array_unique(array_filter([
            $locale,
            setting('default_locale', 'en'),
            'en',
            ...supportedLocaleKeys(),
        ])));
    }

    /**
     * Group rendered notification translations by locale.
     *
     * @param  array<string, string>  $titleTranslations
     * @param  array<string, string>  $subjectTranslations
     * @param  array<string, string>  $contentTranslations
     * @return array<string, array{title: string|null, subject: string|null, content: string|null}>
     */
    protected function notificationTranslations(array $titleTranslations, array $subjectTranslations, array $contentTranslations): array
    {
        $locales = array_values(array_unique(array_filter([
            ...array_keys($titleTranslations),
            ...array_keys($subjectTranslations),
            ...array_keys($contentTranslations),
        ])));

        $translations = [];
        foreach ($locales as $locale) {
            $translations[(string) $locale] = [
                'title' => $titleTranslations[$locale] ?? null,
                'subject' => $subjectTranslations[$locale] ?? null,
                'content' => $contentTranslations[$locale] ?? null,
            ];
        }

        return $translations;
    }

    /**
     * Get a localized value using the configured fallback order.
     *
     * @param  array<string, string>  $translations
     * @return array{0: string|null, 1: string}
     */
    protected function localizedValue(array $translations, string $locale): array
    {
        foreach ($this->localeFallbacks($locale) as $fallbackLocale) {
            $value = $translations[$fallbackLocale] ?? null;
            if (! blank($value)) {
                return [(string) $value, $fallbackLocale];
            }
        }

        foreach ($translations as $translationLocale => $value) {
            if (! blank($value)) {
                return [(string) $value, $translationLocale];
            }
        }

        return [null, $locale];
    }
}
