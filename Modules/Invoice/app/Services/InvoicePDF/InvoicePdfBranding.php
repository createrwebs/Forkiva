<?php

namespace Modules\Invoice\Services\InvoicePDF;

use ArPHP\I18N\Arabic;
use FontLib\Font;

class InvoicePdfBranding
{
    /**
     * Fit the app name into the A4 brand column using the bundled Cairo glyph advances.
     * Explicit logical lines prevent DOMPDF from wrapping already-shaped Arabic backwards.
     *
     * @return array{lines: list<string>, font_size: int, line_height: int}
     */
    public function layout(string $name): array
    {
        $font = Font::load(storage_path('fonts/Cairo-Bold.ttf'));
        $font->parse();
        $characters = $font->getUnicodeCharMap();
        $metrics = $font->getData('hmtx');
        $units = $font->getData('head', 'unitsPerEm');
        $font->close();
        $arabic = new Arabic;

        $measure = static function (string $text, int $size) use ($characters, $metrics, $units, $arabic): float {
            if (preg_match('/\p{Arabic}/u', $text)) {
                $text = $arabic->utf8Glyphs($text, max(1000, mb_strlen($text)), false);
            }

            $advance = 0;
            foreach (mb_str_split($text) as $character) {
                $glyph = $characters[mb_ord($character)] ?? 0;
                $advance += $metrics[$glyph][0] ?? $metrics[0][0];
            }

            return $advance / $units * $size;
        };

        // Leave clearance inside the 52.5% brand column for engine rounding and kerning.
        $maxWidth = 240;
        $size = $measure($name, 21) <= $maxWidth ? 21 : 18;
        $lines = [];
        $line = '';

        foreach (preg_split('/\s+/u', trim($name), -1, PREG_SPLIT_NO_EMPTY) as $word) {
            $candidate = $line === '' ? $word : $line.' '.$word;
            if ($line !== '' && $measure($candidate, $size) > $maxWidth) {
                $lines[] = $line;
                $line = '';
            }

            if ($measure($word, $size) > $maxWidth) {
                foreach (mb_str_split($word) as $character) {
                    if ($line !== '' && $measure($line.$character, $size) > $maxWidth) {
                        $lines[] = $line;
                        $line = '';
                    }
                    $line .= $character;
                }
            } else {
                $line = $line === '' ? $word : $line.' '.$word;
            }
        }

        if ($line !== '') {
            $lines[] = $line;
        }

        return ['lines' => $lines, 'font_size' => $size, 'line_height' => $size === 21 ? 28 : 24];
    }
}
