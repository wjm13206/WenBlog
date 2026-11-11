<?php

declare(strict_types=1);

namespace App\Core;

/**
 * Self-contained, XSS-safe Markdown renderer.
 *
 * Supports headings, paragraphs, emphasis, links, images, blockquotes,
 * ordered/unordered lists, tables, horizontal rules, fenced code, inline code
 * and autolinks. Raw HTML is always escaped.
 */
final class Markdown
{
    /**
     * Static entry point used by helpers and controllers.
     */
    public static function html(string $text): string
    {
        return (new self())->render($text);
    }

    /**
     * Static entry point for plain-text previews and excerpts.
     */
    public static function text(string $text, int $limit = 0): string
    {
        return (new self())->plain($text, $limit);
    }

    public function render(string $text): string
    {
        $text = Str::normalizeNewlines($text);
        $text = str_replace(["\x1A", "\x1B"], '', $text);
        $text = str_replace("\t", '    ', $text);

        $codeBlocks = [];
        $text = preg_replace_callback(
            '/^```([^\n`]*)\n(.*?)^```[ \t]*$/ms',
            static function (array $match) use (&$codeBlocks): string {
                $language = preg_replace('/[^A-Za-z0-9_+#-]/', '', trim($match[1])) ?? '';
                $code = rtrim($match[2], "\n");
                $class = $language !== '' ? ' class="language-' . htmlspecialchars($language, ENT_QUOTES, 'UTF-8') . '"' : '';
                $key = "\x1A" . count($codeBlocks) . "\x1A";
                $codeBlocks[$key] = '<pre><code' . $class . '>'
                    . htmlspecialchars($code, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')
                    . '</code></pre>';

                return "\n\n" . $key . "\n\n";
            },
            $text
        ) ?? $text;

        $html = $this->parseBlocks(explode("\n", $text));

        return strtr($html, $codeBlocks);
    }

    /**
     * Plain text version, useful for feeds and excerpts.
     */
    public function plain(string $text, int $limit = 0): string
    {
        $plain = trim(preg_replace('/\s+/u', ' ', strip_tags($this->render($text))) ?? '');
        if ($limit > 0 && mb_strlen($plain, 'UTF-8') > $limit) {
            return rtrim(mb_substr($plain, 0, $limit, 'UTF-8')) . '…';
        }

        return $plain;
    }

    /** @param list<string> $lines */
    private function parseBlocks(array $lines): string
    {
        $html = [];
        $count = count($lines);
        $i = 0;

        while ($i < $count) {
            $line = $lines[$i];

            if (trim($line) === '') {
                $i++;
                continue;
            }

            if (preg_match('/^\x1A\d+\x1A$/', trim($line)) === 1) {
                $html[] = trim($line);
                $i++;
                continue;
            }

            if (preg_match('/^(#{1,6})\s+(.+?)\s*#*\s*$/u', $line, $match) === 1) {
                $level = strlen($match[1]);
                $html[] = '<h' . $level . '>' . $this->inline($match[2]) . '</h' . $level . '>';
                $i++;
                continue;
            }

            if (preg_match('/^\s*([-*_])(?:\s*\1){2,}\s*$/', $line) === 1) {
                $html[] = '<hr>';
                $i++;
                continue;
            }

            if (preg_match('/^\s*>/', $line) === 1) {
                $quoted = [];
                while ($i < $count && preg_match('/^\s*>\s?(.*)$/', $lines[$i], $match) === 1) {
                    $quoted[] = $match[1];
                    $i++;
                }
                $html[] = '<blockquote>' . $this->parseBlocks($quoted) . '</blockquote>';
                continue;
            }

            if ($this->isListStart($line)) {
                $html[] = $this->parseList($lines, $i);
                continue;
            }

            if ($this->isTableStart($lines, $i)) {
                $html[] = $this->parseTable($lines, $i);
                continue;
            }

            $buffer = [];
            while ($i < $count) {
                $current = $lines[$i];
                if (trim($current) === '') {
                    break;
                }
                if ($buffer !== [] && $this->startsBlock($lines, $i)) {
                    break;
                }
                $buffer[] = $current;
                $i++;
            }
            if ($buffer === []) {
                $buffer[] = $line;
                $i++;
            }
            $html[] = '<p>' . $this->inline(implode("\n", $buffer)) . '</p>';
        }

        return implode("\n", $html);
    }

    /** @param list<string> $lines */
    private function isTableStart(array $lines, int $index): bool
    {
        if (!isset($lines[$index + 1]) || !str_contains($lines[$index], '|')) {
            return false;
        }
        $separator = trim($lines[$index + 1]);
        if (!str_contains($separator, '-')) {
            return false;
        }

        return preg_match('/^\|?\s*:?-{3,}:?\s*(\|\s*:?-{3,}:?\s*)+\|?$/', $separator) === 1;
    }

    /** @param list<string> $lines */
    private function parseTable(array $lines, int &$index): string
    {
        $headers = $this->splitTableRow($lines[$index]);
        $separatorCells = $this->splitTableRow($lines[$index + 1]);
        $alignments = [];
        foreach ($separatorCells as $cell) {
            $left = str_starts_with(trim($cell), ':');
            $right = str_ends_with(trim($cell), ':');
            $alignments[] = $left && $right ? 'center' : ($right ? 'right' : ($left ? 'left' : ''));
        }

        $index += 2;
        $rows = [];
        $count = count($lines);
        while ($index < $count && trim($lines[$index]) !== '' && str_contains($lines[$index], '|')) {
            $rows[] = $this->splitTableRow($lines[$index]);
            $index++;
        }

        $out = '<div class="table-wrap"><table><thead><tr>';
        foreach ($headers as $cellIndex => $cell) {
            $align = $alignments[$cellIndex] ?? '';
            $style = $align !== '' ? ' style="text-align:' . $align . '"' : '';
            $out .= '<th' . $style . '>' . $this->inline($cell) . '</th>';
        }
        $out .= '</tr></thead><tbody>';
        foreach ($rows as $row) {
            $out .= '<tr>';
            foreach ($headers as $cellIndex => $_header) {
                $align = $alignments[$cellIndex] ?? '';
                $style = $align !== '' ? ' style="text-align:' . $align . '"' : '';
                $out .= '<td' . $style . '>' . $this->inline($row[$cellIndex] ?? '') . '</td>';
            }
            $out .= '</tr>';
        }
        $out .= '</tbody></table></div>';

        return $out;
    }

    /** @return list<string> */
    private function splitTableRow(string $line): array
    {
        $line = trim($line);
        $line = preg_replace('/^\|/', '', $line) ?? $line;
        $line = preg_replace('/\|$/', '', $line) ?? $line;
        $cells = array_map('trim', explode('|', $line));

        return array_values($cells);
    }

    /** @param list<string> $lines */
    private function parseList(array $lines, int &$index): string
    {
        $first = $this->listMatch($lines[$index]);
        if ($first === null) {
            return '';
        }
        $ordered = $first['ordered'];
        $baseIndent = $first['indent'];
        $start = $first['number'];
        $items = [];

        $count = count($lines);
        while ($index < $count) {
            $line = $lines[$index];
            if (trim($line) === '') {
                break;
            }
            $match = $this->listMatch($line);
            if ($match !== null && $match['indent'] === $baseIndent && $match['ordered'] === $ordered) {
                $items[] = $match['text'];
                $index++;
                continue;
            }
            if ($match === null && $items !== [] && preg_match('/^\s{2,}(.+)$/', $line, $continuation) === 1) {
                $items[count($items) - 1] .= "\n" . trim($continuation[1]);
                $index++;
                continue;
            }
            break;
        }

        $tag = $ordered ? 'ol' : 'ul';
        $startAttr = $ordered && $start !== 1 ? ' start="' . $start . '"' : '';
        $out = '<' . $tag . $startAttr . '>';
        foreach ($items as $item) {
            $parts = explode("\n", $item);
            $content = $this->inline(array_shift($parts) ?? '');
            if ($parts !== []) {
                $content .= '<br>' . $this->inline(implode("\n", $parts));
            }
            $out .= '<li>' . $content . '</li>';
        }
        $out .= '</' . $tag . '>';

        return $out;
    }

    /** @return array{ordered: bool, indent: int, number: int, text: string}|null */
    private function listMatch(string $line): ?array
    {
        if (preg_match('/^(\s*)([-*+])\s+(.+)$/u', $line, $match) === 1) {
            return ['ordered' => false, 'indent' => strlen($match[1]), 'number' => 1, 'text' => $match[3]];
        }
        if (preg_match('/^(\s*)(\d+)\.\s+(.+)$/u', $line, $match) === 1) {
            return ['ordered' => true, 'indent' => strlen($match[1]), 'number' => (int) $match[2], 'text' => $match[3]];
        }

        return null;
    }

    private function isListStart(string $line): bool
    {
        return $this->listMatch($line) !== null;
    }

    /** @param list<string> $lines */
    private function startsBlock(array $lines, int $index): bool
    {
        $line = $lines[$index];

        if (preg_match('/^(#{1,6})\s+/u', $line) === 1
            || preg_match('/^\s*>/', $line) === 1
            || preg_match('/^\s*([-*_])(?:\s*\1){2,}\s*$/', $line) === 1
            || $this->isListStart($line)
            || preg_match('/^\x1A\d+\x1A$/', trim($line)) === 1) {
            return true;
        }

        return $this->isTableStart($lines, $index);
    }

    private function inline(string $text): string
    {
        $tokens = [];
        $store = static function (string $html) use (&$tokens): string {
            $key = "\x1B" . count($tokens) . "\x1B";
            $tokens[$key] = $html;

            return $key;
        };

        $text = str_replace(["\x1A", "\x1B"], '', $text);

        $text = preg_replace_callback('/`([^`\n]+)`/', static function (array $match) use ($store): string {
            return $store('<code>' . htmlspecialchars($match[1], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '</code>');
        }, $text) ?? $text;

        $text = htmlspecialchars($text, ENT_NOQUOTES | ENT_SUBSTITUTE, 'UTF-8');

        $text = preg_replace_callback(
            '/!\[([^\]]*)\]\(\s*([^\s)]+)(?:\s+"([^"]*)")?\s*\)/u',
            function (array $match) use ($store): string {
                $url = $this->safeUrl($match[2], true);
                $alt = htmlspecialchars(html_entity_decode($match[1], ENT_QUOTES, 'UTF-8'), ENT_QUOTES, 'UTF-8');
                if ($url === null) {
                    return $store($alt);
                }
                $title = isset($match[3]) && $match[3] !== ''
                    ? ' title="' . htmlspecialchars(html_entity_decode($match[3], ENT_QUOTES, 'UTF-8'), ENT_QUOTES, 'UTF-8') . '"'
                    : '';

                return $store('<img src="' . htmlspecialchars($url, ENT_QUOTES, 'UTF-8') . '" alt="' . $alt . '"' . $title . ' loading="lazy">');
            },
            $text
        ) ?? $text;

        $text = preg_replace_callback(
            '/\[([^\]]+)\]\(\s*([^\s)]+)(?:\s+"([^"]*)")?\s*\)/u',
            function (array $match) use ($store): string {
                $url = $this->safeUrl($match[2], false);
                $label = $this->emphasis($match[1]);
                if ($url === null) {
                    return $store($label);
                }
                $title = isset($match[3]) && $match[3] !== ''
                    ? ' title="' . htmlspecialchars(html_entity_decode($match[3], ENT_QUOTES, 'UTF-8'), ENT_QUOTES, 'UTF-8') . '"'
                    : '';
                $external = preg_match('#^https?://#i', $url) === 1;

                return $store(
                    '<a href="' . htmlspecialchars($url, ENT_QUOTES, 'UTF-8') . '"' . $title
                    . ($external ? ' rel="nofollow noopener"' : '') . '>' . $label . '</a>'
                );
            },
            $text
        ) ?? $text;

        $text = preg_replace_callback('/&lt;(https?:\/\/[^\s&]+)&gt;/', static function (array $match) use ($store): string {
            $url = html_entity_decode($match[1], ENT_QUOTES, 'UTF-8');

            return $store('<a href="' . htmlspecialchars($url, ENT_QUOTES, 'UTF-8') . '" rel="nofollow noopener">' . htmlspecialchars($url, ENT_NOQUOTES, 'UTF-8') . '</a>');
        }, $text) ?? $text;

        $text = $this->emphasis($text);
        $text = str_replace("  \n", "<br>\n", $text);

        return strtr($text, $tokens);
    }

    private function emphasis(string $text): string
    {
        $text = preg_replace('/~~(.+?)~~/su', '<del>$1</del>', $text) ?? $text;
        $text = preg_replace('/\*\*(.+?)\*\*/su', '<strong>$1</strong>', $text) ?? $text;
        $text = preg_replace('/__(.+?)__/su', '<strong>$1</strong>', $text) ?? $text;
        $text = preg_replace('/(?<!\*)\*(?!\*)(.+?)(?<!\*)\*(?!\*)/su', '<em>$1</em>', $text) ?? $text;
        $text = preg_replace('/(?<![\p{L}\p{N}_])_([^_]+)_(?![\p{L}\p{N}_])/su', '<em>$1</em>', $text) ?? $text;

        return $text;
    }

    private function safeUrl(string $url, bool $image): ?string
    {
        $url = trim(html_entity_decode($url, ENT_QUOTES, 'UTF-8'));
        if ($url === '' || preg_match('/[\x00-\x20]/', $url) === 1) {
            return null;
        }
        if (preg_match('/^(?:javascript|data|vbscript|file):/i', $url) === 1) {
            return null;
        }
        if (str_starts_with($url, '//')) {
            return null;
        }
        if (str_starts_with($url, '/') || str_starts_with($url, '#') || str_starts_with($url, '?')) {
            return $url;
        }
        $scheme = strtolower((string) parse_url($url, PHP_URL_SCHEME));
        if ($scheme === '') {
            return $url;
        }
        $allowed = $image ? ['http', 'https'] : ['http', 'https', 'mailto'];

        return in_array($scheme, $allowed, true) ? $url : null;
    }
}