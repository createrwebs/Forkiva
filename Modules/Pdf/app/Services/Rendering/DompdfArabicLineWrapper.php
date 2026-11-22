<?php

namespace Modules\Pdf\Services\Rendering;

use DOMDocument;
use DOMElement;
use Dompdf\Dompdf;
use Dompdf\Frame;
use Dompdf\Options;

class DompdfArabicLineWrapper
{
    /**
     * Wrap text in logical order before shaping each visual line.
     * A local layout pass supplies the actual CSS widths and embedded font metrics.
     *
     * @param  callable(string): string  $shape
     */
    public function process(string $html, float $paperWidthMm, callable $shape, array $margins = []): string
    {
        $document = new DOMDocument('1.0', 'UTF-8');
        $document->loadHTML('<?xml encoding="UTF-8">'.$html, LIBXML_NONET | LIBXML_NOERROR | LIBXML_NOWARNING);
        $elements = [];
        foreach ($document->getElementsByTagName('*') as $element) {
            if (in_array($element->tagName, ['script', 'style', 'title'], true)
                || preg_match('/[\x{0600}-\x{06FF}]/u', $element->textContent) !== 1) {
                continue;
            }

            // Keep containers and independently styled descendants intact.
            foreach ($element->childNodes as $child) {
                if ($child instanceof DOMElement && $child->tagName !== 'br'
                    && ! ($child->tagName === 'span' && in_array($child->getAttribute('class'), ['number', 'mono'], true))) {
                    continue 2;
                }
            }
            $id = (string) count($elements);
            $element->setAttribute('data-dompdf-wrap-id', $id);
            $elements[$id] = $element;
        }

        $options = new Options;
        $options->setIsRemoteEnabled(false);
        $probe = new Dompdf($options);
        $probe->setPaper([0, 0, $paperWidthMm * 72 / 25.4, 2000 * 72 / 25.4]);
        $probeHtml = $document->saveHTML($document->documentElement);
        if ($margins !== []) {
            // Match the final driver's margin override when measuring table cells.
            $unit = $margins['unit'] ?? 'mm';
            $values = array_map(fn (string $edge): string => $margins[$edge].$unit, ['top', 'right', 'bottom', 'left']);
            $probeHtml = str_replace('</head>', '<style>@page { margin: '.implode(' ', $values).'; }</style></head>', $probeHtml);
        }
        $probe->loadHtml(preg_replace_callback(
            '/(?<=>)([^<]*\p{Arabic}[^<]*)(?=<)/u',
            fn ($match) => htmlspecialchars($shape(html_entity_decode($match[1], ENT_QUOTES | ENT_HTML5, 'UTF-8')), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'),
            $probeHtml,
        ));
        $boxes = [];
        $probe->setCallbacks([[
            'event' => 'begin_frame',
            'f' => static function (Frame $frame) use (&$boxes): void {
                $node = $frame->get_node();
                $style = $frame->get_style();
                if (! $node instanceof DOMElement || ! $node->hasAttribute('data-dompdf-wrap-id')
                    || ! in_array($style->display, ['block', 'table-cell'], true)) {
                    return;
                }
                $boxes[$node->getAttribute('data-dompdf-wrap-id')] = [
                    'width' => $frame->get_content_box()['w'],
                    'font' => $style->font_family,
                    'size' => $style->font_size,
                    'white_space' => $style->white_space,
                    'word_spacing' => $style->word_spacing,
                    'letter_spacing' => $style->letter_spacing,
                ];
            },
        ]]);
        $probe->render();

        foreach ($boxes as $id => $box) {
            if ($box['width'] <= 0) {
                continue;
            }
            $element = $elements[$id];
            $text = '';
            $hasBreaks = false;
            foreach ($element->childNodes as $child) {
                if ($child instanceof DOMElement && $child->tagName === 'br') {
                    $text .= "\n";
                    $hasBreaks = true;
                } else {
                    $text .= $child->textContent;
                }
            }
            $paragraphs = $hasBreaks || in_array($box['white_space'], ['pre', 'pre-line', 'pre-wrap'], true)
                ? preg_split('/\R/u', trim($text)) : [$text];
            $measure = fn (string $line): float => $probe->getFontMetrics()->getTextWidth(
                $shape($line), $box['font'], $box['size'], $box['word_spacing'], $box['letter_spacing'],
            );
            $lines = [];
            foreach ($paragraphs as $paragraph) {
                $line = '';
                foreach (preg_split('/\s+/u', trim($paragraph), -1, PREG_SPLIT_NO_EMPTY) as $word) {
                    $candidate = $line === '' ? $word : $line.' '.$word;
                    if ($line !== '' && $measure($candidate) > $box['width'] - 0.2) {
                        $lines[] = $line;
                        $line = '';
                    }
                    if ($measure($word) > $box['width'] - 0.2) {
                        preg_match_all('/\X/u', $word, $characters);
                        foreach ($characters[0] as $character) {
                            if ($line !== '' && $measure($line.$character) > $box['width'] - 0.2) {
                                $lines[] = $line;
                                $line = '';
                            }
                            $line .= $character;
                        }
                    } else {
                        $line .= ($line === '' ? '' : ' ').$word;
                    }
                }
                $lines[] = $line;
            }

            while ($element->firstChild) {
                $element->removeChild($element->firstChild);
            }
            foreach ($lines as $index => $line) {
                if ($index > 0) {
                    $element->appendChild($document->createElement('br'));
                }
                $span = $document->createElement('span');
                $span->setAttribute('style', 'white-space: nowrap');
                $span->setAttribute('data-dompdf-line', $line);
                $span->appendChild($document->createTextNode($line));
                $element->appendChild($span);
            }
        }

        foreach ($elements as $element) {
            $element->removeAttribute('data-dompdf-wrap-id');
        }

        return '<!DOCTYPE html>'.$document->saveHTML($document->documentElement);
    }
}
