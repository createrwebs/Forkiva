<?php

namespace Modules\Notification\Support;

class NotificationPayload
{
    /**
     * Build the canonical persisted notification payload.
     *
     * @param  array<string, mixed>  $rendered
     * @return array<string, mixed>
     */
    public static function fromRendered(array $rendered): array
    {
        $translations = self::translations($rendered['translations'] ?? []);

        $payload = [
            'type' => (string) ($rendered['type'] ?? 'notification'),
            'type_id' => $rendered['type_id'] ?? null,
            'translations' => $translations,
        ];

        if (! blank($rendered['app_context'] ?? null)) {
            $payload['app_context'] = (string) $rendered['app_context'];
        }

        return $payload;
    }

    /**
     * Normalize notification translations.
     *
     * @return array<string, array{title: string|null, subject: string|null, content: string|null}>
     */
    protected static function translations(mixed $value): array
    {
        if (! is_array($value)) {
            return [];
        }

        $translations = [];
        foreach ($value as $locale => $translation) {
            if (! is_array($translation)) {
                continue;
            }

            $translations[(string) $locale] = [
                'title' => self::nullableString($translation['title'] ?? null),
                'subject' => self::nullableString($translation['subject'] ?? null),
                'content' => self::nullableString($translation['content'] ?? null),
            ];
        }

        return $translations;
    }

    /**
     * Normalize a nullable scalar string.
     */
    protected static function nullableString(mixed $value): ?string
    {
        return blank($value) ? null : (string) $value;
    }

    /**
     * Build compact string-only push data values.
     *
     * @param  array<string, mixed>  $payload
     * @return array<string, string>
     */
    public static function forPushData(array $payload, ?string $locale = null, ?string $notificationId = null): array
    {
        $clientPayload = self::forClient($payload, $locale);

        return [
            'notification_id' => (string) $notificationId,
            'type' => (string) ($clientPayload['type'] ?? ''),
            'type_id' => (string) ($clientPayload['type_id'] ?? ''),
            'app_context' => (string) ($clientPayload['app_context'] ?? ''),
            'title' => self::plain($clientPayload['title'] ?? null),
            'subject' => self::plain($clientPayload['subject'] ?? null),
            'content' => self::plain($clientPayload['content'] ?? null),
        ];
    }

    /**
     * Build the client-facing payload shape used by API resources and push data.
     *
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    public static function forClient(array $payload, ?string $locale = null): array
    {
        $locale = $locale ?: locale();
        $translations = self::translations($payload['translations'] ?? []);

        return [
            'type' => $payload['type'] ?? null,
            'type_id' => $payload['type_id'] ?? null,
            'app_context' => $payload['app_context'] ?? null,
            'title' => self::localized($translations, 'title', $locale),
            'subject' => self::localized($translations, 'subject', $locale),
            'content' => self::localized($translations, 'content', $locale),
        ];
    }

    /**
     * Resolve a localized notification field from translations.
     *
     * @param  array<string, array<string, string|null>>  $translations
     */
    protected static function localized(array $translations, string $field, string $locale): ?string
    {
        if ($translations === []) {
            return null;
        }

        foreach (self::localeFallbacks($locale) as $fallbackLocale) {
            $value = self::translationFieldValue($translations[$fallbackLocale] ?? null, $field);
            if ($value !== null) {
                return $value;
            }
        }

        foreach ($translations as $translation) {
            $value = self::translationFieldValue($translation, $field);
            if ($value !== null) {
                return $value;
            }
        }

        return null;
    }

    /**
     * Normalize one translated field value.
     *
     * @param  array<string, string|null>|mixed  $translation
     */
    protected static function translationFieldValue(mixed $translation, string $field): ?string
    {
        if (! is_array($translation)) {
            return null;
        }

        return self::nullableString($translation[$field] ?? null);
    }

    /**
     * Get locale fallback order.
     *
     * @return array<int, string>
     */
    protected static function localeFallbacks(string $locale): array
    {
        return array_values(array_unique(array_filter([
            $locale,
            setting('default_locale', 'en'),
            'en',
            ...supportedLocaleKeys(),
        ])));
    }

    /**
     * Get a plain display string.
     */
    public static function plain(mixed $value): string
    {
        return trim(strip_tags((string) $value));
    }
}
