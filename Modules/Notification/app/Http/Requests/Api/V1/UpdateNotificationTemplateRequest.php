<?php

namespace Modules\Notification\Http\Requests\Api\V1;

use Illuminate\Validation\Validator;
use InvalidArgumentException;
use Modules\Core\Http\Requests\Request;
use Modules\Notification\Database\Seeders\NotificationTemplates\NotificationRequirementTemplateCatalog;
use Modules\Notification\Models\NotificationTemplate;
use Modules\Notification\Models\NotificationTemplateMessage;
use Modules\Notification\Services\NotificationChannel\NotificationChannelResolverService;
use Modules\Notification\Services\NotificationChannel\NotificationChannelResolverServiceInterface;
use Modules\Notification\Support\NotificationWorkflowRecipientCatalog;

class UpdateNotificationTemplateRequest extends Request
{
    private const VARIABLE_PATTERN = '/\{\{\s*([A-Za-z_][A-Za-z0-9_]*)\s*\}\}|\{([A-Za-z_][A-Za-z0-9_]*)\}/';

    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        return [
            ...$this->getTranslationRules([
                'name' => 'required|string|max:255',
                'description' => 'nullable|string|max:1000',
            ]),
            'messages' => ['nullable', 'array'],
            'messages.*' => ['required', 'array'],
        ];
    }

    /**
     * Get the validated data from the request.
     *
     * @param  array|string|int|null  $key
     * @param  mixed  $default
     */
    public function validated($key = null, $default = null): mixed
    {
        $validated = parent::validated($key, $default);

        if (! is_null($key) || ! is_array($validated)) {
            return $validated;
        }

        if ($this->has('messages')) {
            $validated['messages'] = $this->input('messages', []);
        }

        return $validated;
    }

    /**
     * Configure the validator instance.
     */
    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            $template = NotificationTemplate::query()
                ->where('key', $this->route('key'))
                ->first();

            if (! $template) {
                $validator->errors()->add('key', __('notification::notification_templates.errors.template_not_found'));

                return;
            }

            $channelKeys = app(NotificationChannelResolverServiceInterface::class)->keys();
            $templateChannelKeys = $this->templateChannelKeys($template);
            $messages = $this->input('messages', []);
            $defaultLocale = setting('default_locale', 'en');
            $supportedLocales = supportedLocaleKeys();

            foreach ($messages as $channel => $message) {
                $channel = (string) $channel;
                $variants = $this->normalizeMessageVariants(is_array($message) ? $message : []);

                if (! in_array($channel, $channelKeys, true)) {
                    $validator->errors()->add("messages.$channel", __('notification::notification_templates.errors.unsupported_channel'));

                    continue;
                }

                if (! in_array($channel, $templateChannelKeys, true)) {
                    $validator->errors()->add("messages.$channel", __('notification::notification_templates.errors.unsupported_channel'));

                    continue;
                }

                foreach ($variants as $role => $variant) {
                    $path = "messages.$channel.$role";

                    if (! $this->recipientRoleSupported($template->key, $channel, $role)) {
                        $validator->errors()->add($path, __('notification::notification_workflows.errors.unsupported_recipient'));

                        continue;
                    }

                    $this->validateMessageShape($validator, $channel, $variant, $path);
                    $this->validateTranslationLocales($validator, $channel, $variant, $supportedLocales, $defaultLocale, $path);
                    $this->validateSupportedVariables($validator, $template, $channel, $variant, $path);
                    $this->validateDefaultLocalePlaceholders($validator, $channel, $variant, $defaultLocale, $path);
                }
            }
        });
    }

    /**
     * Get supported channel keys for the template.
     *
     * @return array<int, string>
     */
    protected function templateChannelKeys(NotificationTemplate $template): array
    {
        try {
            return NotificationRequirementTemplateCatalog::messageChannelKeys(
                NotificationRequirementTemplateCatalog::get($template->key)
            );
        } catch (InvalidArgumentException) {
            return $template->messages()
                ->pluck('channel_key')
                ->all();
        }
    }

    /**
     * Normalize legacy channel messages and new role-variant messages.
     *
     * @param  array<string, mixed>  $message
     * @return array<string, array<string, mixed>>
     */
    protected function normalizeMessageVariants(array $message): array
    {
        if ($this->looksLikeMessage($message)) {
            return [NotificationTemplateMessage::DEFAULT_RECIPIENT_ROLE => $message];
        }

        $variants = [];

        foreach ($message as $role => $variant) {
            if (is_array($variant)) {
                $variants[$role] = $variant;
            }
        }

        return $variants;
    }

    /**
     * Determine whether an array is one message rather than role-keyed variants.
     *
     * @param  array<string, mixed>  $message
     */
    protected function looksLikeMessage(array $message): bool
    {
        return array_key_exists('content', $message)
            || array_key_exists('subject', $message)
            || array_key_exists('title', $message);
    }

    /**
     * Check whether a recipient role can have a message variant for the channel.
     */
    protected function recipientRoleSupported(string $templateKey, string $channel, string $role): bool
    {
        if ($role === NotificationTemplateMessage::DEFAULT_RECIPIENT_ROLE) {
            return true;
        }

        return in_array($role, NotificationWorkflowRecipientCatalog::allowedRolesFor($templateKey, $channel), true);
    }

    /**
     * Validate channel-specific message shape.
     *
     * @param  array<string, mixed>  $message
     */
    protected function validateMessageShape(Validator $validator, string $channel, array $message, string $path): void
    {
        $isEmail = $channel === NotificationChannelResolverService::EMAIL_CHANNEL;

        if ($isEmail) {
            return;
        }

        if ($this->hasFilledTranslation($message['subject'] ?? null)) {
            $validator->errors()->add(
                "$path.subject",
                __('notification::notification_templates.errors.subject_email_only')
            );
        }
    }

    /**
     * Determine if a translation object has any filled value.
     */
    protected function hasFilledTranslation(mixed $translations): bool
    {
        if (! is_array($translations)) {
            return ! blank($translations);
        }

        foreach ($translations as $translation) {
            if (! blank($translation)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Validate translation locale keys.
     *
     * @param  array<string, mixed>  $message
     * @param  array<int, string>  $supportedLocales
     */
    protected function validateTranslationLocales(
        Validator $validator,
        string $channel,
        array $message,
        array $supportedLocales,
        string $defaultLocale,
        string $path,
    ): void {
        foreach ($this->messageFields($channel, $message) as $field => $translations) {
            foreach ($translations as $locale => $value) {
                if (! in_array($locale, $supportedLocales, true)) {
                    $validator->errors()->add(
                        "$path.$field.$locale",
                        __('validation.in', ['attribute' => "$path.$field.$locale"])
                    );
                }
            }
        }

        if (blank($message['content'][$defaultLocale] ?? null)) {
            $validator->errors()->add(
                "$path.content.$defaultLocale",
                __('validation.required', ['attribute' => "$path.content.$defaultLocale"])
            );
        }
    }

    /**
     * Get message fields that can contain variables.
     *
     * @param  array<string, mixed>  $message
     * @return array<string, mixed>
     */
    protected function messageFields(string $channel, array $message): array
    {
        $fields = [
            'title' => is_array($message['title'] ?? null) ? $message['title'] : [],
            'content' => is_array($message['content'] ?? null) ? $message['content'] : [],
        ];

        if ($channel === NotificationChannelResolverService::EMAIL_CHANNEL) {
            $fields['subject'] = is_array($message['subject'] ?? null) ? $message['subject'] : [];
        }

        return $fields;
    }

    /**
     * Validate variables used by the submitted fields.
     *
     * @param  array<string, mixed>  $message
     */
    protected function validateSupportedVariables(
        Validator $validator,
        NotificationTemplate $template,
        string $channel,
        array $message,
        string $path,
    ): void {
        foreach ($this->messageFields($channel, $message) as $field => $translations) {
            foreach ($translations as $locale => $value) {
                foreach ($this->extractVariables((string) $value) as $variable) {
                    if (! in_array($variable, $template->supported_variables ?? [], true)) {
                        $validator->errors()->add(
                            "$path.$field.$locale",
                            __('notification::notification_templates.errors.unknown_variable', [
                                'variable' => "{{$variable}}",
                            ])
                        );
                    }
                }
            }
        }
    }

    /**
     * Extract variable names from a value.
     *
     * @return array<int, string>
     */
    protected function extractVariables(string $value): array
    {
        preg_match_all(self::VARIABLE_PATTERN, $value, $matches);

        return array_values(array_unique(array_filter([
            ...($matches[1] ?? []),
            ...($matches[2] ?? []),
        ])));
    }

    /**
     * Validate translated placeholders against the default locale message.
     *
     * @param  array<string, mixed>  $message
     */
    protected function validateDefaultLocalePlaceholders(
        Validator $validator,
        string $channel,
        array $message,
        string $defaultLocale,
        string $path,
    ): void {
        foreach ($this->messageFields($channel, $message) as $field => $translations) {
            $defaultValue = (string) ($translations[$defaultLocale] ?? '');
            if (blank($defaultValue)) {
                continue;
            }

            foreach ($translations as $locale => $value) {
                if ($locale === $defaultLocale || blank($value)) {
                    continue;
                }

                $missingVariables = array_diff(
                    $this->extractVariables($defaultValue),
                    $this->extractVariables((string) $value)
                );

                if ($missingVariables === []) {
                    continue;
                }

                $validator->errors()->add(
                    "$path.$field.$locale",
                    __('notification::notification_templates.errors.missing_translation_variables', [
                        'variables' => implode(', ', array_map(fn (string $variable) => "{{$variable}}", $missingVariables)),
                    ])
                );
            }
        }
    }

    /** {@inheritDoc} */
    protected function availableAttributes(): string
    {
        return 'notification::attributes.notification_templates';
    }
}
