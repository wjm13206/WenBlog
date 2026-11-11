<?php

declare(strict_types=1);

namespace App\Core;

use RuntimeException;
use Throwable;
use ZipArchive;

/**
 * Theme discovery, activation, configuration and template rendering.
 */
final class Theme
{
    private string $directory;
    private ?array $metaCache = null;
    /** @var array<string, mixed> Data of the template currently being rendered. */
    private array $renderData = [];
    /** Lazily-created Typecho-compatible API bound to the current render. */
    private ?ThemeApi $api = null;

    public function __construct(private readonly App $app)
    {
        $this->directory = BASE_PATH . '/themes';
    }

    public function boot(): void
    {
        if ($this->requiresTypechoCompat()) {
            require_once BASE_PATH . '/app/typecho.php';
        }

        $functions = $this->directory . '/' . $this->active() . '/functions.php';
        if (is_file($functions)) {
            try {
                include_once $functions;
            } catch (Throwable $e) {
                Logger::error('Theme bootstrap failed: ' . $e->getMessage());
            }
        }
        Hook::do('theme.booted', $this);
    }

    /**
     * Native Typecho themes guard every file with `__TYPECHO_ROOT_DIR__` and
     * expect Typecho's global API. Detect that pattern before loading their
     * functions.php so the compatibility bootstrap can run first.
     */
    private function requiresTypechoCompat(): bool
    {
        $directory = $this->directory . '/' . $this->active();
        foreach (['index.php', 'functions.php'] as $name) {
            $file = $directory . '/' . $name;
            if (!is_file($file)) {
                continue;
            }
            $head = @file_get_contents($file, false, null, 0, 8192);
            if (is_string($head) && str_contains($head, '__TYPECHO_ROOT_DIR__')) {
                return true;
            }
        }

        return false;
    }

    public function active(): string
    {
        $theme = (string) $this->app->options->get('active_theme', 'default');
        if (preg_match('/^[a-zA-Z0-9_-]+$/', $theme) !== 1 || !is_file($this->directory . '/' . $theme . '/index.php')) {
            $theme = 'default';
        }

        return $theme;
    }

    /** @param array<string, mixed> $data */
    public function render(string $template, array $data = []): string
    {
        $template = preg_replace('/[^a-zA-Z0-9_-]/', '', $template) ?? 'index';
        $root = $this->directory . '/' . $this->active();
        // Typecho themes name their templates differently: a single post is
        // post.php and every archive (category, tag, search) is archive.php.
        // Accept both the WenBlog and Typecho names, then fall back to index.
        $candidates = [$template . '.php'];
        if ($template === 'single') {
            $candidates[] = 'post.php';
        }
        if (in_array($template, ['category', 'tag', 'search'], true)) {
            $candidates[] = 'archive.php';
        }
        $candidates[] = 'index.php';

        $file = '';
        foreach ($candidates as $candidate) {
            if (is_file($root . '/' . $candidate)) {
                $file = $root . '/' . $candidate;
                break;
            }
        }
        if ($file === '') {
            throw new RuntimeException('主题模板不存在。');
        }

        $data['theme'] = $this;
        $previousData = $this->renderData;
        $this->renderData = $data;
        // Every render gets a fresh API so loop cursors never leak between
        // the main template and partials from a previous request.
        $this->api = null;
        extract($data, EXTR_SKIP);
        ob_start();
        try {
            include $file;

            return (string) ob_get_clean();
        } catch (Throwable $e) {
            ob_end_clean();
            throw $e;
        } finally {
            $this->renderData = $previousData;
        }
    }

    /**
     * Data of the template currently being rendered. With a key, the matching
     * value is returned (or null when it does not exist).
     */
    public function data(?string $key = null): mixed
    {
        if ($key === null) {
            return $this->renderData;
        }

        return $this->renderData[$key] ?? null;
    }

    /**
     * Typecho-compatible API object. Templates use `$this->...` directly; this
     * accessor is exposed for partials and plugins that need an explicit handle.
     */
    public function api(): ThemeApi
    {
        return $this->api ??= new ThemeApi($this, $this->app);
    }

    public function __call(string $name, array $arguments): mixed
    {
        return $this->api()->{$name}(...$arguments);
    }

    public function __get(string $name): mixed
    {
        return $this->api()->{$name};
    }

    public function __isset(string $name): bool
    {
        return isset($this->api()->{$name});
    }

    /**
     * Include a theme file with the current template scope. Typecho themes
     * keep header.php / footer.php in the theme root, while WenBlog themes use
     * partials/; both are supported, with or without the .php suffix.
     */
    public function need(string $name): void
    {
        $name = str_replace('\\', '/', trim($name));
        if ($name === '' || str_contains($name, '..')
            || preg_match('#^[a-zA-Z0-9_./-]+$#', $name) !== 1) {
            return;
        }
        $name = ltrim($name, '/');
        $candidates = str_ends_with($name, '.php')
            ? [$name, 'partials/' . $name]
            : [$name . '.php', 'partials/' . $name . '.php'];

        foreach ($candidates as $candidate) {
            $file = $this->directory . '/' . $this->active() . '/' . $candidate;
            if (!is_file($file)) {
                continue;
            }
            $data = $this->renderData;
            $data['theme'] = $this;
            extract($data, EXTR_SKIP);
            include $file;

            return;
        }

        Logger::warning('Theme template not found: ' . $name);
    }

    public function hasTemplate(string $template): bool
    {
        $template = preg_replace('/[^a-zA-Z0-9_-]/', '', $template) ?? '';
        if ($template === '' || $template === '401' || $template === '403' || $template === '404') {
            return false;
        }
        $file = $this->directory . '/' . $this->active() . '/' . $template . '.php';
        if (!is_file($file)) {
            return false;
        }

        return $template === 'page' || $this->isPageTemplateFile($file);
    }

    /** @return list<string> */
    public function templates(): array
    {
        $templates = [];
        foreach (glob($this->directory . '/' . $this->active() . '/*.php') ?: [] as $file) {
            $name = basename($file, '.php');
            if (in_array($name, ['index', '404', 'functions'], true)) {
                continue;
            }
            if ($name === 'page' || $this->isPageTemplateFile($file)) {
                $templates[] = $name;
            }
        }
        sort($templates);

        return $templates;
    }

    /**
     * A custom page template must opt in with a "Template Name:" header so
     * article, category, tag and archive templates can never be selected by
     * mistake on a static page.
     */
    private function isPageTemplateFile(string $file): bool
    {
        $head = @file_get_contents($file, false, null, 0, 8192);

        return is_string($head) && preg_match('/Template\s+Name\s*:/i', $head) === 1;
    }

    /** @param array<string, mixed> $data */
    public function partial(string $name, array $data = []): void
    {
        $name = preg_replace('/[^a-zA-Z0-9_-]/', '', $name) ?? '';
        $file = $this->directory . '/' . $this->active() . '/partials/' . $name . '.php';
        if (!is_file($file)) {
            return;
        }
        // Partials inherit the current template scope so themes can call
        // $theme->partial('header') without manually forwarding every variable.
        $data = array_merge($this->renderData, $data);
        $data['theme'] = $this;
        extract($data, EXTR_SKIP);
        include $file;
    }

    /** @return array<string, mixed> */
    public function metadata(?string $slug = null): array
    {
        $slug ??= $this->active();
        if ($slug === $this->active() && $this->metaCache !== null) {
            return $this->metaCache;
        }
        $file = $this->directory . '/' . $slug . '/theme.json';
        $data = is_file($file) ? json_decode((string) file_get_contents($file), true) : null;
        $meta = [
            'slug'          => $slug,
            'name'          => (string) ($data['name'] ?? $slug),
            'version'       => (string) ($data['version'] ?? '1.0.0'),
            'description'   => (string) ($data['description'] ?? ''),
            'author'        => (string) ($data['author'] ?? ''),
            'config'        => is_array($data['config'] ?? null) ? $data['config'] : [],
            // Optional group metadata used by the admin settings page:
            // "config_groups": { "basic": { "label": "基础信息", ... } }
            'config_groups' => is_array($data['config_groups'] ?? null) ? $data['config_groups'] : [],
        ];
        if ($slug === $this->active()) {
            $this->metaCache = $meta;
        }

        return $meta;
    }

    /**
     * Normalised configuration fields declared in theme.json.
     *
     * A theme can keep the minimal form:
     *
     *     "config": { "colorScheme": { "default": "pink" } }
     *
     * or describe the field for the admin settings page:
     *
     *     "config": {
     *         "colorScheme": {
     *             "label": "配色方案",
     *             "description": "选择站点使用的主题色。",
     *             "type": "radio",
     *             "group": "appearance",
     *             "default": "pink",
     *             "options": { "pink": "素粉", "blue": "墨蓝" }
     *         }
     *     }
     *
     * @return list<array<string, mixed>>
     */
    public function configFields(?string $slug = null): array
    {
        $meta = $this->metadata($slug);
        $fields = [];
        foreach ($meta['config'] as $key => $field) {
            $key = (string) $key;
            // Older theme.json files store the default value directly.
            $field = is_array($field) ? $field : ['default' => $field];
            $options = [];
            if (is_array($field['options'] ?? null)) {
                foreach ($field['options'] as $optionValue => $optionLabel) {
                    $options[(string) $optionValue] = (string) $optionLabel;
                }
            }
            $type = strtolower(trim((string) ($field['type'] ?? '')));
            if ($type === '') {
                $type = $options !== [] ? 'radio' : 'text';
            }
            if (!in_array($type, self::CONFIG_FIELD_TYPES, true)) {
                $type = 'text';
            }
            $default = $field['default'] ?? '';
            $fields[] = [
                'key'         => $key,
                'label'       => (string) ($field['label'] ?? $key),
                'description' => (string) ($field['description'] ?? ''),
                'type'        => $type,
                'default'     => is_scalar($default) ? (string) $default : '',
                'options'     => $options,
                'placeholder' => (string) ($field['placeholder'] ?? ''),
                'group'       => (string) ($field['group'] ?? 'general'),
                'rows'        => max(2, (int) ($field['rows'] ?? 4)),
                'wide'        => (bool) ($field['wide'] ?? in_array($type, ['textarea', 'radio'], true)),
            ];
        }

        return $fields;
    }

    /**
     * Configuration fields grouped for the settings page, in declaration
     * order. Group labels come from theme.json's `config_groups` block and
     * fall back to the group key.
     *
     * @return list<array{key: string, label: string, description: string, fields: list<array<string, mixed>>}>
     */
    public function configGroups(?string $slug = null): array
    {
        $groupMeta = $this->metadata($slug)['config_groups'];
        $groups = [];
        foreach ($this->configFields($slug) as $field) {
            $groupKey = (string) $field['group'];
            if (!isset($groups[$groupKey])) {
                $info = is_array($groupMeta[$groupKey] ?? null) ? $groupMeta[$groupKey] : [];
                $groups[$groupKey] = [
                    'key'         => $groupKey,
                    'label'       => (string) ($info['label'] ?? $groupKey),
                    'description' => (string) ($info['description'] ?? ''),
                    'fields'      => [],
                ];
            }
            $groups[$groupKey]['fields'][] = $field;
        }

        return array_values($groups);
    }

    /**
     * Apply posted values to the declared fields and persist them. Only keys
     * declared in theme.json are touched, and radio/select values must exist
     * in their option list so a crafted request cannot store arbitrary data.
     *
     * @param array<string, mixed> $input
     * @return array<string, mixed> Saved configuration.
     */
    public function saveConfigValues(array $input): array
    {
        $saved = $this->savedConfig();
        foreach ($this->configFields() as $field) {
            $key = (string) $field['key'];
            $type = (string) $field['type'];
            if ($type === 'checkbox') {
                $saved[$key] = !empty($input[$key]) ? '1' : '0';
                continue;
            }
            if (!array_key_exists($key, $input)) {
                continue;
            }
            $raw = $input[$key];
            $value = is_scalar($raw) ? trim((string) $raw) : '';
            if (in_array($type, ['radio', 'select'], true)
                && $field['options'] !== []
                && !array_key_exists($value, $field['options'])) {
                continue;
            }
            $saved[$key] = $value;
        }
        $this->saveConfig($saved);

        return $saved;
    }

    /** @var list<string> */
    private const CONFIG_FIELD_TYPES = [
        'text', 'textarea', 'checkbox', 'radio', 'select',
        'color', 'number', 'url', 'email', 'password',
    ];

    /** @return list<array<string, mixed>> */
    public function all(): array
    {
        $themes = [];
        foreach (glob($this->directory . '/*', GLOB_ONLYDIR) ?: [] as $dir) {
            $slug = basename($dir);
            if (!is_file($dir . '/index.php')) {
                continue;
            }
            $meta = $this->metadata($slug);
            $meta['active'] = $slug === $this->active();
            $meta['screenshot'] = is_file($dir . '/screenshot.png')
                ? url('themes/' . rawurlencode($slug) . '/screenshot.png')
                : '';
            $themes[] = $meta;
        }

        return $themes;
    }

    public function activate(string $slug): void
    {
        if (preg_match('/^[a-zA-Z0-9_-]+$/', $slug) !== 1 || !is_file($this->directory . '/' . $slug . '/index.php')) {
            throw new RuntimeException('主题不存在。');
        }
        $this->app->options->set('active_theme', $slug);
        $this->metaCache = null;
        $this->app->cache->clear();
        Hook::do('theme.activated', $slug);
    }

    public function config(string $key, mixed $default = null): mixed
    {
        $saved = $this->savedConfig();
        if (array_key_exists($key, $saved)) {
            return $saved[$key];
        }
        $fields = $this->metadata()['config'];
        if (isset($fields[$key]) && is_array($fields[$key]) && array_key_exists('default', $fields[$key])) {
            return $fields[$key]['default'];
        }

        return $default;
    }

    /** @return array<string, mixed> */
    public function savedConfig(): array
    {
        $raw = $this->app->options->get('theme_config_' . $this->active(), '{}');
        $data = is_string($raw) ? json_decode($raw, true) : null;

        return is_array($data) ? $data : [];
    }

    /** @param array<string, mixed> $config */
    public function saveConfig(array $config): void
    {
        $this->app->options->set('theme_config_' . $this->active(), json_encode($config, JSON_UNESCAPED_UNICODE));
        $this->app->cache->clear();
    }

    public function installFromZip(array $file): string
    {
        if (!class_exists(ZipArchive::class)) {
            throw new RuntimeException('服务器未启用 ZipArchive 扩展。');
        }
        if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
            throw new RuntimeException('文件上传失败。');
        }
        $zip = new ZipArchive();
        if ($zip->open((string) ($file['tmp_name'] ?? '')) !== true) {
            throw new RuntimeException('无法打开 ZIP 文件。');
        }

        $work = BASE_PATH . '/storage/cache/theme-' . bin2hex(random_bytes(6));
        @mkdir($work, 0775, true);
        try {
            for ($index = 0; $index < $zip->numFiles; $index++) {
                $name = (string) $zip->getNameIndex($index);
                if ($name === ''
                    || str_contains($name, "\0")
                    || str_contains($name, '..')
                    || str_starts_with($name, '/')
                    || str_contains($name, '\\')
                    || preg_match('#^[A-Za-z]:#', $name) === 1) {
                    throw new RuntimeException('压缩包包含不安全的路径。');
                }
            }
            $zip->extractTo($work);
            $zip->close();

            $root = $this->findThemeRoot($work);
            $meta = json_decode((string) file_get_contents($root . '/theme.json'), true);
            $meta = is_array($meta) ? $meta : [];
            $name = (string) ($meta['slug'] ?? '');
            if ($name === '') {
                $name = Str::slug((string) ($meta['name'] ?? basename($root)));
            }
            $name = Str::slug($name);
            if ($name === '') {
                throw new RuntimeException('主题包缺少有效名称。');
            }
            $target = $this->directory . '/' . $name;
            if (is_dir($target)) {
                throw new RuntimeException('同名主题已存在。');
            }
            $this->copyDirectory($root, $target);
            if (!is_file($target . '/index.php')) {
                $this->deleteDirectory($target);
                throw new RuntimeException('主题缺少 index.php。');
            }

            return $name;
        } finally {
            $this->deleteDirectory($work);
        }
    }

    public function delete(string $slug): void
    {
        if (preg_match('/^[a-zA-Z0-9_-]+$/', $slug) !== 1) {
            throw new RuntimeException('主题标识无效。');
        }
        if ($slug === $this->active() || $slug === 'default') {
            throw new RuntimeException('不能删除当前启用的主题。');
        }
        $this->deleteDirectory($this->directory . '/' . $slug);
    }

    private function findThemeRoot(string $directory): string
    {
        if (is_file($directory . '/theme.json')) {
            return $directory;
        }
        foreach (glob($directory . '/*', GLOB_ONLYDIR) ?: [] as $child) {
            if (is_file($child . '/theme.json')) {
                return $child;
            }
        }

        throw new RuntimeException('压缩包中没有找到主题目录。');
    }

    private function copyDirectory(string $source, string $target): void
    {
        @mkdir($target, 0775, true);
        foreach (scandir($source) ?: [] as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }
            $from = $source . '/' . $item;
            $to = $target . '/' . $item;
            is_dir($from) ? $this->copyDirectory($from, $to) : copy($from, $to);
        }
    }

    private function deleteDirectory(string $directory): void
    {
        if (!is_dir($directory)) {
            return;
        }
        foreach (scandir($directory) ?: [] as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }
            $path = $directory . '/' . $item;
            is_dir($path) ? $this->deleteDirectory($path) : @unlink($path);
        }
        @rmdir($directory);
    }
}