<?php

namespace Modules\Notification\Support;

use Symfony\Component\HtmlSanitizer\HtmlSanitizer;
use Symfony\Component\HtmlSanitizer\HtmlSanitizerConfig;

class NotificationContentSanitizer
{
    protected HtmlSanitizer $htmlSanitizer;

    /**
     * Create the sanitizer used for notification email markup.
     */
    public function __construct()
    {
        $config = new HtmlSanitizerConfig;

        foreach (['p', 'br', 'strong', 'b', 'em', 'i', 'u', 'ul', 'ol', 'li'] as $element) {
            $config = $config->allowElement($element);
        }

        $this->htmlSanitizer = new HtmlSanitizer($config);
    }

    /**
     * Sanitize one trusted-format email body.
     */
    public function html(?string $value): ?string
    {
        return $value === null ? null : $this->htmlSanitizer->sanitize($value);
    }

    /**
     * Convert one notification value to plain display text.
     */
    public function plain(?string $value): ?string
    {
        if ($value === null) {
            return null;
        }

        return html_entity_decode(
            strip_tags($value),
            ENT_QUOTES | ENT_HTML5,
            'UTF-8',
        );
    }

    /**
     * Escape a variable before inserting it into an HTML template.
     */
    public function escapeHtmlVariable(string $value): string
    {
        return htmlspecialchars(
            $value,
            ENT_QUOTES | ENT_SUBSTITUTE | ENT_HTML5,
            'UTF-8',
        );
    }

    /**
     * Sanitize translated values while retaining their locale keys.
     */
    public function translations(mixed $translations, bool $html): mixed
    {
        if (! is_array($translations)) {
            return is_string($translations)
                ? ($html ? $this->html($translations) : $this->plain($translations))
                : $translations;
        }

        foreach ($translations as $locale => $value) {
            if (is_string($value)) {
                $translations[$locale] = $html ? $this->html($value) : $this->plain($value);
            }
        }

        return $translations;
    }
}
