<?php

declare(strict_types=1);

use App\Core\App;
use App\Core\Markdown;
use App\Core\Response;
use App\Core\Session;
use App\Core\Str;

if (!function_exists('app')) {
    /**
     * Access the shared application container.
     */
    function app(): ?App
    {
        return $GLOBALS['app'] ?? null;
    }
}

if (!function_exists('config')) {
    function config(string $key, mixed $default = null): mixed
    {
        $app = app();
        if ($app === null) {
            return $default;
        }

        return $app->config($key, $default);
    }
}

if (!function_exists('option')) {
    function option(string $key, mixed $default = null): mixed
    {
        $app = app();
        if ($app === null) {
            return $default;
        }

        return $app->options->get($key, $default);
    }
}

if (!function_exists('e')) {
    /**
     * Escape a value for HTML output.
     */
    function e(mixed $value): string
    {
        return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
}

if (!function_exists('url')) {
    /**
     * Build an absolute URL from a site-relative path.
     */
    function url(string $path = ''): string
    {
        $app = $GLOBALS['app'] ?? null;
        if ($app instanceof \App\Core\App && !$app->isInstalled()) {
            // Before installation site_url is unknown and config.sample.php may
            // still contain a placeholder host; use the current request path.
            $base = rtrim($app->request->basePath(), '/');
        } else {
            $base = trim((string) option('site_url', ''));
            if ($base === '') {
                $base = (string) config('app.url', '');
            }
            if ($base === '' && $app instanceof \App\Core\App) {
                $base = rtrim($app->request->basePath(), '/');
            }
            $base = rtrim($base, '/');
        }

        if ($path === '') {
            return $base . '/';
        }
        if (preg_match('#^(?:https?:)?//#i', $path) === 1 || str_starts_with($path, 'mailto:')) {
            return $path;
        }
        if (str_starts_with($path, '#')) {
            return $base . '/' . $path;
        }

        return $base . '/' . ltrim($path, '/');
    }
}

if (!function_exists('admin_url')) {
    function admin_url(string $path = ''): string
    {
        return $path === '' ? url('admin') : url('admin/' . ltrim($path, '/'));
    }
}

if (!function_exists('asset')) {
    /**
     * URL for a core asset, with a small cache-busting version.
     */
    function asset(string $path): string
    {
        $path = ltrim($path, '/');
        if (!str_starts_with($path, 'assets/')) {
            $path = 'assets/' . $path;
        }
        $file = BASE_PATH . '/' . $path;
        $version = is_file($file) ? (string) filemtime($file) : (string) config('app.version', '1.0.0');

        return url($path) . '?v=' . rawurlencode($version);
    }
}

if (!function_exists('theme_asset')) {
    function theme_asset(string $path): string
    {
        $theme = app()?->theme->active() ?? 'default';
        $file = BASE_PATH . '/themes/' . $theme . '/' . ltrim($path, '/');
        $version = is_file($file) ? (string) filemtime($file) : '1';

        return url('themes/' . rawurlencode($theme) . '/' . ltrim($path, '/')) . '?v=' . rawurlencode($version);
    }
}

if (!function_exists('upload_url')) {
    function upload_url(string $path): string
    {
        if (preg_match('#^https?://#i', $path) === 1) {
            return $path;
        }

        return url(ltrim($path, '/'));
    }
}

if (!function_exists('post_url')) {
    function post_url(array $post): string
    {
        $slug = rawurlencode((string) ($post['slug'] ?? ''));
        $style = (string) option('permalink_style', 'post');

        return $style === 'slug' ? url($slug) : url('post/' . $slug);
    }
}

if (!function_exists('page_url')) {
    function page_url(array $page): string
    {
        return url(rawurlencode((string) ($page['slug'] ?? '')));
    }
}

if (!function_exists('category_url')) {
    function category_url(array $category): string
    {
        return url('category/' . rawurlencode((string) ($category['slug'] ?? '')));
    }
}

if (!function_exists('tag_url')) {
    function tag_url(array $tag): string
    {
        return url('tag/' . rawurlencode((string) ($tag['slug'] ?? '')));
    }
}

if (!function_exists('csrf_token')) {
    function csrf_token(): string
    {
        $app = app();

        return $app === null ? '' : $app->security->csrfToken();
    }
}

if (!function_exists('csrf_field')) {
    function csrf_field(): string
    {
        return '<input type="hidden" name="_token" value="' . e(csrf_token()) . '">';
    }
}

if (!function_exists('redirect')) {
    function redirect(string $path, int $status = 302): never
    {
        Response::redirect($path, $status);
    }
}

if (!function_exists('back')) {
    function back(string $fallback = '/admin'): never
    {
        $referer = (string) ($_SERVER['HTTP_REFERER'] ?? '');
        $host = parse_url($referer, PHP_URL_HOST);
        $ownHost = parse_url(url('/'), PHP_URL_HOST);
        if ($referer !== '' && $host !== null && $host === $ownHost) {
            Response::redirect($referer);
        }

        Response::redirect(str_starts_with($fallback, 'http') ? $fallback : url($fallback));
    }
}

if (!function_exists('current_user')) {
    function current_user(): ?array
    {
        return app()?->auth->user();
    }
}

if (!function_exists('is_admin')) {
    function is_admin(): bool
    {
        return app()?->auth->isAdmin() ?? false;
    }
}

if (!function_exists('flash')) {
    function flash(string $type, string $message): void
    {
        app()?->session->flash($type, $message);
    }
}

if (!function_exists('old')) {
    function old(string $key, mixed $default = ''): mixed
    {
        $input = app()?->session->oldInput() ?? [];

        return array_key_exists($key, $input) ? $input[$key] : $default;
    }
}

if (!function_exists('errors')) {
    /**
     * @return array<string, string>
     */
    function errors(): array
    {
        return app()?->session->errors() ?? [];
    }
}

if (!function_exists('pagination')) {
    function pagination(\App\Core\Paginator $pagination): string
    {
        if (!$pagination->hasPages()) {
            return '';
        }
        $html = '<nav class="pagination" aria-label="分页">';
        if ($pagination->hasPrevious()) {
            $html .= '<a class="page-link" href="' . e($pagination->url($pagination->current() - 1)) . '" rel="prev" aria-label="上一页">‹</a>';
        } else {
            $html .= '<span class="page-link disabled" aria-hidden="true">‹</span>';
        }
        foreach ($pagination->pages() as $item) {
            if ($item === '…') {
                $html .= '<span class="page-ellipsis">…</span>';
            } else {
                $active = (int) $item === $pagination->current();
                $html .= '<a class="page-link' . ($active ? ' active' : '') . '" href="' . e($pagination->url((int) $item)) . '"'
                    . ($active ? ' aria-current="page"' : '') . '>' . (int) $item . '</a>';
            }
        }
        if ($pagination->current() < $pagination->lastPage()) {
            $html .= '<a class="page-link" href="' . e($pagination->url($pagination->current() + 1)) . '" rel="next" aria-label="下一页">›</a>';
        } else {
            $html .= '<span class="page-link disabled" aria-hidden="true">›</span>';
        }

        return $html . '</nav>';
    }
}

if (!function_exists('format_bytes')) {
    function format_bytes(int $bytes): string
    {
        if ($bytes < 1024) {
            return $bytes . ' B';
        }
        if ($bytes < 1048576) {
            return number_format($bytes / 1024, 1) . ' KB';
        }

        return number_format($bytes / 1048576, 1) . ' MB';
    }
}

if (!function_exists('format_date')) {
    function format_date(?string $date, ?string $format = null): string
    {
        if ($date === null || $date === '' || $date === '0000-00-00 00:00:00') {
            return '';
        }
        $timestamp = strtotime($date);

        return $timestamp === false ? '' : date($format ?? (string) option('date_format', 'Y年n月j日'), $timestamp);
    }
}

if (!function_exists('human_time')) {
    function human_time(?string $date): string
    {
        if ($date === null || $date === '') {
            return '';
        }
        $timestamp = strtotime($date);
        if ($timestamp === false) {
            return '';
        }
        $diff = time() - $timestamp;
        if ($diff < 60) {
            return '刚刚';
        }
        if ($diff < 3600) {
            return (int) floor($diff / 60) . ' 分钟前';
        }
        if ($diff < 86400) {
            return (int) floor($diff / 3600) . ' 小时前';
        }
        if ($diff < 2592000) {
            return (int) floor($diff / 86400) . ' 天前';
        }

        return format_date($date);
    }
}

if (!function_exists('excerpt_text')) {
    function excerpt_text(string $text, int $length = 160): string
    {
        $plain = trim(preg_replace('/\s+/u', ' ', strip_tags($text)) ?? '');
        if (mb_strlen($plain, 'UTF-8') <= $length) {
            return $plain;
        }

        return rtrim(mb_substr($plain, 0, $length, 'UTF-8')) . '…';
    }
}

if (!function_exists('post_excerpt')) {
    function post_excerpt(array $post, ?int $length = null): string
    {
        $length ??= (int) option('excerpt_length', 160);
        $excerpt = trim((string) ($post['excerpt'] ?? ''));
        if ($excerpt !== '') {
            return excerpt_text($excerpt, $length);
        }

        return excerpt_text(\App\Core\Markdown::text((string) ($post['content'] ?? '')), $length);
    }
}

if (!function_exists('search_terms')) {
    /**
     * Break a query into highlightable terms. Long CJK words also expand into
     * two-character fragments so n-gram matches still light up.
     *
     * @return list<string>
     */
    function search_terms(string $query): array
    {
        $query = trim($query);
        if ($query === '') {
            return [];
        }

        $terms = [];
        foreach (preg_split('/\s+/u', $query) ?: [] as $part) {
            $part = trim((string) $part);
            if (mb_strlen($part, 'UTF-8') >= 2) {
                $terms[] = $part;
            }
        }
        if ($terms === [] && mb_strlen($query, 'UTF-8') >= 2) {
            $terms[] = $query;
        }

        $expanded = [];
        foreach ($terms as $term) {
            $expanded[] = $term;
            if (preg_match('/^[\x{4e00}-\x{9fff}]+$/u', $term) === 1 && mb_strlen($term, 'UTF-8') > 4) {
                $chars = preg_split('//u', $term, -1, PREG_SPLIT_NO_EMPTY) ?: [];
                $count = count($chars);
                for ($index = 0; $index + 1 < $count; $index += 1) {
                    $expanded[] = $chars[$index] . $chars[$index + 1];
                }
            }
        }

        $expanded = array_values(array_unique(array_filter(
            $expanded,
            static fn (string $item): bool => $item !== ''
        )));

        usort($expanded, static fn (string $a, string $b): int => mb_strlen($b, 'UTF-8') <=> mb_strlen($a, 'UTF-8'));

        return array_slice($expanded, 0, 24);
    }
}

if (!function_exists('search_highlight')) {
    /**
     * Escape text and wrap matched terms in <mark> tags.
     */
    function search_highlight(string $text, string $query): string
    {
        $terms = search_terms($query);
        if ($text === '' || $terms === []) {
            return e($text);
        }

        $quoted = array_map(static fn (string $term): string => preg_quote($term, '/'), $terms);
        $parts = preg_split('/(' . implode('|', $quoted) . ')/iu', $text, -1, PREG_SPLIT_DELIM_CAPTURE);
        if ($parts === false) {
            return e($text);
        }

        $needles = array_map(static fn (string $term): string => mb_strtolower($term, 'UTF-8'), $terms);
        $html = '';
        foreach ($parts as $part) {
            if ($part === '') {
                continue;
            }
            $html .= in_array(mb_strtolower($part, 'UTF-8'), $needles, true)
                ? '<mark>' . e($part) . '</mark>'
                : e($part);
        }

        return $html;
    }
}

if (!function_exists('search_snippet')) {
    /**
     * Trim text around the first hit and highlight it.
     */
    function search_snippet(string $text, string $query, int $length = 160): string
    {
        $plain = trim(preg_replace('/\s+/u', ' ', $text) ?? '');
        if ($plain === '') {
            return '';
        }

        $start = 0;
        foreach (search_terms($query) as $term) {
            $position = mb_stripos($plain, $term, 0, 'UTF-8');
            if ($position !== false) {
                $start = max(0, $position - (int) ($length / 3));
                break;
            }
        }

        $total = mb_strlen($plain, 'UTF-8');
        $snippet = mb_substr($plain, $start, $length, 'UTF-8');
        if ($start > 0) {
            $snippet = '…' . $snippet;
        }
        if ($start + $length < $total) {
            $snippet .= '…';
        }

        return search_highlight($snippet, $query);
    }
}

if (!function_exists('markdown')) {
    function markdown(string $text): string
    {
        return \App\Core\Markdown::html($text);
    }
}
if (!function_exists('reading_minutes')) {
    function reading_minutes(string $content): int
    {
        $plain = strip_tags(markdown($content));
        $cjk = preg_match_all('/[\x{4e00}-\x{9fff}]/u', $plain);
        $words = str_word_count(preg_replace('/[\x{4e00}-\x{9fff}]/u', ' ', $plain) ?? '');
        $minutes = (int) ceil(((int) $cjk + $words) / 350);

        return max(1, $minutes);
    }
}

if (!function_exists('truncate')) {
    function truncate(string $text, int $length = 80, string $suffix = '…'): string
    {
        if (mb_strlen($text, 'UTF-8') <= $length) {
            return $text;
        }

        return mb_substr($text, 0, $length, 'UTF-8') . $suffix;
    }
}

if (!function_exists('status_label')) {
    function status_label(string $status): string
    {
        return match ($status) {
            'published' => '已发布',
            'scheduled' => '定时发布',
            'draft' => '草稿',
            default => $status,
        };
    }
}

if (!function_exists('comment_status_label')) {
    function comment_status_label(string $status): string
    {
        return match ($status) {
            'approved' => '已通过',
            'pending' => '待审核',
            'spam' => '垃圾评论',
            default => $status,
        };
    }
}

if (!function_exists('role_label')) {
    function role_label(string $role): string
    {
        return $role === 'admin' ? '管理员' : '编辑';
    }
}

if (!function_exists('initials')) {
    function initials(string $name): string
    {
        $name = trim($name);
        if ($name === '') {
            return '?';
        }

        return mb_strtoupper(mb_substr($name, 0, 1, 'UTF-8'), 'UTF-8');
    }
}

if (!function_exists('selected')) {
    function selected(mixed $value, mixed $expected): string
    {
        return (string) $value === (string) $expected ? ' selected' : '';
    }
}

if (!function_exists('checked')) {
    function checked(mixed $value): string
    {
        return (bool) $value ? ' checked' : '';
    }
}

if (!function_exists('is_active')) {
    function is_active(string $prefix, string $class = 'active'): string
    {
        $path = app()?->request->path() ?? '/';
        if ($prefix === '/admin') {
            return $path === '/admin' ? $class : '';
        }

        return str_starts_with($path, $prefix) ? $class : '';
    }
}

if (!function_exists('theme_config')) {
    function theme_config(string $key, mixed $default = null): mixed
    {
        return app()?->theme->config($key, $default) ?? $default;
    }
}

if (!function_exists('theme_partial')) {
    function theme_partial(string $name, array $data = []): void
    {
        app()?->theme->partial($name, $data);
    }
}

if (!function_exists('icon')) {
    /**
     * Inline SVG icon from the bundled Feather-style set.
     */
    function icon(string $name, int $size = 18, string $class = ''): string
    {
        static $icons = null;
        if ($icons === null) {
            $icons = require BASE_PATH . '/app/icons.php';
        }
        $paths = $icons[$name] ?? $icons['circle'];
        $classAttr = trim('icon ' . $class);

        return '<svg class="' . e($classAttr) . '" width="' . $size . '" height="' . $size
            . '" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" '
            . 'stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">' . $paths . '</svg>';
    }
}

if (!function_exists('json_safe')) {
    function json_safe(mixed $value): string
    {
        return (string) json_encode(
            $value,
            JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT
        );
    }
}

if (!function_exists('str_slug')) {
    function str_slug(string $value): string
    {
        return Str::slug($value);
    }
}