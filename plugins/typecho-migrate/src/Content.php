<?php

declare(strict_types=1);

namespace WenBlog\Plugins\TypechoMigrate;

use App\Core\Markdown;

/**
 * Converts Typecho post bodies into WenBlog's Markdown storage format.
 *
 * Typecho marks Markdown bodies with a leading `<!--markdown-->` comment. When
 * the marker is absent the body is HTML (from the WYSIWYG editor), so common
 * block and inline tags are converted to Markdown instead of being stored as
 * escaped text.
 */
final class Content
{
    public static function toMarkdown(string $text): string
    {
        $text = str_replace(["\r\n", "\r"], "\n", $text);
        $text = preg_replace('/^\xEF\xBB\xBF/', '', $text) ?? $text;

        $hadMarker = preg_match('/^\s*<!--\s*markdown\s*-->/i', $text) === 1;
        $text = preg_replace('/^\s*<!--\s*markdown\s*-->/i', '', $text) ?? $text;
        $text = str_replace(['<!--more-->', '<!--nextpage-->'], '', $text);

        if (!$hadMarker && self::looksLikeHtml($text)) {
            $text = self::htmlToMarkdown($text);
        }

        $text = preg_replace("/\n{3,}/u", "\n\n", $text) ?? $text;

        return trim($text);
    }

    /**
     * Typecho stores the "read more" cut inside the body. Reuse the text before
     * it as the WenBlog excerpt when present.
     */
    public static function excerptFrom(string $text): ?string
    {
        $text = str_replace(["\r\n", "\r"], "\n", $text);
        if (preg_match('/<!--more-->/i', $text) !== 1) {
            return null;
        }
        $parts = preg_split('/<!--more-->/i', $text, 2);
        $plain = trim(Markdown::text(self::toMarkdown((string) ($parts[0] ?? ''))));
        if ($plain === '') {
            return null;
        }

        return mb_substr($plain, 0, 200, 'UTF-8');
    }

    private static function looksLikeHtml(string $text): bool
    {
        return preg_match('/<(p|div|section|article|h[1-6]|ul|ol|li|blockquote|pre|table|figure|img|br)\b/i', $text) === 1;
    }

    private static function htmlToMarkdown(string $html): string
    {
        if (!class_exists(\DOMDocument::class)) {
            $text = preg_replace('#<(br|/p|/div|/h[1-6]|/li)>#i', "\n", $html) ?? $html;
            $text = html_entity_decode(strip_tags($text), ENT_QUOTES | ENT_HTML5, 'UTF-8');

            return trim($text);
        }

        $previous = libxml_use_internal_errors(true);
        $document = new \DOMDocument('1.0', 'UTF-8');
        $document->loadHTML(
            '<?xml encoding="utf-8" ?><html><body>' . $html . '</body></html>',
            LIBXML_NOWARNING | LIBXML_NOERROR
        );
        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        $body = $document->getElementsByTagName('body')->item(0);
        if ($body === null) {
            return trim(strip_tags($html));
        }

        $markdown = self::children($body);
        $markdown = preg_replace("/[ \t]+\n/u", "\n", $markdown) ?? $markdown;
        $markdown = preg_replace("/\n{3,}/u", "\n\n", $markdown) ?? $markdown;

        return trim($markdown);
    }

    private static function children(\DOMNode $node): string
    {
        $result = '';
        foreach ($node->childNodes as $child) {
            $result .= self::node($child);
        }

        return $result;
    }

    private static function node(\DOMNode $node): string
    {
        if ($node->nodeType === XML_TEXT_NODE || $node->nodeType === XML_CDATA_SECTION_NODE) {
            return (string) $node->nodeValue;
        }
        if ($node->nodeType !== XML_ELEMENT_NODE) {
            return '';
        }

        $tag = strtolower($node->nodeName);
        if (in_array($tag, ['script', 'style', 'noscript', 'head', 'title', 'meta', 'link'], true)) {
            return '';
        }

        $inner = self::children($node);

        switch ($tag) {
            case 'br':
                return "\n";
            case 'hr':
                return "\n\n---\n\n";
            case 'h1':
            case 'h2':
            case 'h3':
            case 'h4':
            case 'h5':
            case 'h6':
                return "\n\n" . str_repeat('#', (int) substr($tag, 1)) . ' ' . trim($inner) . "\n\n";
            case 'p':
            case 'div':
            case 'section':
            case 'article':
            case 'header':
            case 'footer':
            case 'main':
            case 'figure':
            case 'figcaption':
            case 'address':
            case 'center':
                return "\n\n" . trim($inner) . "\n\n";
            case 'strong':
            case 'b':
                return '**' . trim($inner) . '**';
            case 'em':
            case 'i':
                return '*' . trim($inner) . '*';
            case 'code':
                return '`' . str_replace('`', '\\`', trim($inner)) . '`';
            case 'pre':
                return "\n\n```\n" . rtrim((string) $node->textContent, "\n") . "\n```\n\n";
            case 'blockquote':
                return "\n\n" . self::prefixLines(trim($inner), '> ') . "\n\n";
            case 'a':
                $href = trim((string) $node->getAttribute('href'));
                $label = trim($inner);
                if ($href === '' || str_starts_with(strtolower($href), 'javascript:')) {
                    return $label;
                }
                if (str_contains($href, ' ') || str_contains($href, '(') || str_contains($href, ')')) {
                    $href = '<' . $href . '>';
                }

                return '[' . ($label !== '' ? $label : $href) . '](' . $href . ')';
            case 'img':
                $src = trim((string) $node->getAttribute('src'));
                if ($src === '') {
                    return '';
                }

                return '![' . trim((string) $node->getAttribute('alt')) . '](' . $src . ')';
            case 'ul':
            case 'ol':
                return "\n\n" . self::listMarkdown($node, $tag === 'ol') . "\n\n";
            case 'table':
                return "\n\n" . self::tableMarkdown($node) . "\n\n";
            case 'dl':
            case 'dd':
            case 'dt':
                return "\n\n" . trim($inner) . "\n\n";
            case 'tr':
            case 'td':
            case 'th':
            case 'thead':
            case 'tbody':
            case 'tfoot':
                return trim($inner);
            default:
                return $inner;
        }
    }

    private static function prefixLines(string $text, string $prefix): string
    {
        $lines = explode("\n", $text);
        foreach ($lines as $index => $line) {
            $lines[$index] = $prefix . $line;
        }

        return implode("\n", $lines);
    }

    private static function listMarkdown(\DOMNode $list, bool $ordered): string
    {
        $lines = [];
        $index = 1;
        foreach ($list->childNodes as $child) {
            if ($child->nodeType !== XML_ELEMENT_NODE || strtolower($child->nodeName) !== 'li') {
                continue;
            }
            $content = trim(self::children($child));
            $content = preg_replace("/\n{3,}/u", "\n\n", $content) ?? $content;
            $content = str_replace("\n", "\n  ", $content);
            $prefix = $ordered ? ($index++ . '. ') : '- ';
            $lines[] = $prefix . $content;
        }

        return implode("\n", $lines);
    }

    private static function tableMarkdown(\DOMNode $table): string
    {
        $rows = [];
        foreach ($table->getElementsByTagName('tr') as $tr) {
            $cells = [];
            foreach ($tr->childNodes as $cell) {
                if ($cell->nodeType !== XML_ELEMENT_NODE) {
                    continue;
                }
                $name = strtolower($cell->nodeName);
                if ($name !== 'td' && $name !== 'th') {
                    continue;
                }
                $value = trim(preg_replace('/\s+/u', ' ', self::children($cell)) ?? '');
                $cells[] = str_replace('|', '\\|', $value);
            }
            if ($cells !== []) {
                $rows[] = $cells;
            }
        }
        if ($rows === []) {
            return '';
        }

        $width = max(array_map('count', $rows));
        $lines = [];
        foreach ($rows as $index => $cells) {
            $cells = array_pad($cells, $width, '');
            $lines[] = '| ' . implode(' | ', $cells) . ' |';
            if ($index === 0) {
                $lines[] = '| ' . implode(' | ', array_fill(0, $width, '---')) . ' |';
            }
        }

        return implode("\n", $lines);
    }
}
