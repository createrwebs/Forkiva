<?php

namespace Modules\Pdf\Services\Rendering;

use ArPHP\I18N\Arabic;

class ArabicHtmlProcessor
{
    /**
     * Shape Arabic text nodes for PDF engines without native complex-script support.
     */
    public function process(string $html, ?float $paperWidthMm = null, array $margins = []): string
    {
        if (preg_match('/\p{Arabic}/u', $html) !== 1) {
            return $html;
        }

        $arabic = new Arabic;

        if (preg_match('/data-dompdf-wrap-width-mm="([\d.]+)"/', $html, $width) === 1) {
            $paperWidthMm = (float) $width[1];
        }

        if ($paperWidthMm !== null) {
            $shape = fn (string $text): string => $this->shapeLine($arabic, $text);
            $html = (new DompdfArabicLineWrapper)->process($html, $paperWidthMm, $shape, $margins);

            return preg_replace_callback(
                '/(?<=>)([^<]*\p{Arabic}[^<]*)(?=<)/u',
                fn (array $matches): string => htmlspecialchars($shape(html_entity_decode($matches[1], ENT_QUOTES | ENT_HTML5, 'UTF-8')), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'),
                $html,
            ) ?? $html;
        }

        return preg_replace_callback(
            '/(?<=>)([^<]*\p{Arabic}[^<]*)(?=<)/u',
            fn (array $matches): string => $arabic->utf8Glyphs(
                $matches[1],
                max(1000, mb_strlen($matches[1])),
                false,
            ),
            $html,
        ) ?? $html;
    }

    private function shapeLine(Arabic $arabic, string $text): string
    {
        $runs = [];
        $protected = preg_replace_callback(
            '/[+#-]?[\p{Latin}\p{N}][\p{Latin}\p{N}.,:\/%+@#_()=\-]*(?:[ \t]+[\p{Latin}\p{N}][\p{Latin}\p{N}.,:\/%+@#_()=\-]*)*/u',
            function (array $match) use (&$runs): string {
                $token = 'WRAPLTRTOKEN'.str_repeat('X', count($runs) + 1).'Z';
                $runs[$token] = ' '.$match[0].' ';

                return $token;
            },
            $text,
        );
        $shaped = $arabic->utf8Glyphs($protected, max(1000, mb_strlen($protected)), false, true);

        return trim(preg_replace('/[ \t]+/', ' ', strtr($shaped, $runs)));
    }
}
