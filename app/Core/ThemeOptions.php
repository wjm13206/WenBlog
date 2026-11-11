<?php

declare(strict_types=1);

namespace App\Core;

/**
 * Typecho-compatible option bag.
 *
 * Both calling styles used by Typecho themes work:
 *
 *     <?php $this->options->siteUrl(); ?>
 *     <a href="<?= e($this->options->siteUrl) ?>">…</a>
 *
 * Camel-case names are converted to the snake_case keys stored in the
 * options table, so `$this->options->postsPerPage` maps to `posts_per_page`.
 */
final class ThemeOptions
{
    /** @var array<string, string> */
    private const ALIASES = [
        'title'       => 'site_name',
        'description' => 'site_description',
        'keywords'    => 'seo_keywords',
        'footerText'  => 'footer_text',
        'dateFormat'  => 'date_format',
        'timezone'    => 'timezone',
    ];

    public function __construct(private readonly Theme $theme)
    {
    }

    public function get(string $name, mixed $default = null): mixed
    {
        switch ($name) {
            case 'siteUrl':
            case 'rootUrl':
                return rtrim(url('/'), '/');
            case 'themeUrl':
                return url('themes/' . rawurlencode($this->theme->active()));
            case 'adminUrl':
                return admin_url();
            case 'loginUrl':
                return admin_url('login');
            case 'profileUrl':
                return admin_url('profile');
            case 'logoutUrl':
                return admin_url('logout');
            case 'feedUrl':
                return url('rss');
            case 'searchUrl':
                return url('search');
            case 'charset':
                return 'UTF-8';
            case 'lang':
                return 'zh-CN';
            case 'footerText':
                return $this->footerText();
        }

        $key = self::ALIASES[$name] ?? $this->snakeCase($name);
        $value = option($key, null);
        if ($value === null) {
            // Theme configuration saved through the admin UI or the defaults
            // declared in theme.json.
            $value = $this->theme->config($name, null);
        }
        if ($value === null || (is_string($value) && trim($value) === '')) {
            // Sensible fallbacks for well-known Typecho theme options. Empty
            // strings count too: a blank title/logo/avatar would otherwise
            // render as an empty <img src=""> instead of the site default.
            $fallback = match ($name) {
                'titleIndex'              => option('site_name', $default),
                'logoIndex', 'authorAvatar' => $this->get('themeUrl') . '/images/avatar.webp',
                'footerInfo'              => (string) option('footer_text', ''),
                'commentsRequireModeration' => '0',
                default                   => null,
            };
            if ($fallback !== null) {
                $value = $fallback;
            }
        }

        return $value ?? $default;
    }

    public function __get(string $name): mixed
    {
        return $this->get($name);
    }

    public function __isset(string $name): bool
    {
        return $this->get($name) !== null;
    }

    /**
     * Echo the value, matching Typecho's `$this->options->title()` style.
     * When a path argument is supplied it is appended to the URL, which makes
     * `$this->options->themeUrl('style.css')` work like Typecho.
     */
    public function __call(string $name, array $arguments): void
    {
        $value = $this->get($name);
        if ($arguments !== [] && is_string($value) && $this->isUrlOption($name)) {
            $value = rtrim($value, '/') . '/' . ltrim((string) $arguments[0], '/');
        }
        if (is_scalar($value)) {
            echo (string) $value;
        }
    }

    private function isUrlOption(string $name): bool
    {
        return in_array($name, ['siteUrl', 'rootUrl', 'themeUrl', 'adminUrl', 'loginUrl', 'feedUrl', 'searchUrl'], true);
    }

    private function footerText(): string
    {
        $text = (string) option('footer_text', '');

        return str_replace(
            ['{year}', '{site_name}', '{siteName}'],
            [date('Y'), (string) option('site_name', 'WenBlog'), (string) option('site_name', 'WenBlog')],
            $text
        );
    }

    private function snakeCase(string $name): string
    {
        return strtolower((string) preg_replace('/(?<!^)[A-Z]/', '_$0', $name));
    }
}
