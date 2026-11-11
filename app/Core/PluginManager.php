<?php

declare(strict_types=1);

namespace App\Core;

use RuntimeException;
use Throwable;
use ZipArchive;

/**
 * Filesystem + database plugin registry. Active plugins are included once on
 * boot and register their hooks themselves.
 */
final class PluginManager
{
    private string $directory;

    public function __construct(private readonly App $app)
    {
        $this->directory = BASE_PATH . '/plugins';
        if (!is_dir($this->directory)) {
            @mkdir($this->directory, 0775, true);
        }
    }

    public function loadActive(): void
    {
        try {
            $rows = $this->app->db->fetchAll('SELECT slug, main FROM plugins WHERE active = 1 ORDER BY id');
        } catch (Throwable $e) {
            Logger::warning('Plugin registry unavailable: ' . $e->getMessage());

            return;
        }

        foreach ($rows as $row) {
            $slug = (string) $row['slug'];
            $main = (string) ($row['main'] ?: 'plugin.php');
            if (preg_match('/^[a-zA-Z0-9_-]+$/', $slug) !== 1
                || preg_match('#^[a-zA-Z0-9_./-]+$#', $main) !== 1
                || str_contains($main, '..')) {
                continue;
            }
            $file = $this->directory . '/' . $slug . '/' . $main;
            $real = realpath($file);
            $base = realpath($this->directory . '/' . $slug);
            if ($real === false || $base === false || !str_starts_with($real, $base . DIRECTORY_SEPARATOR)) {
                continue;
            }
            try {
                include_once $file;
            } catch (Throwable $e) {
                Logger::error('Plugin failed to load: ' . $slug, ['error' => $e->getMessage()]);
            }
        }

        Hook::do('plugins.loaded', $this);
    }

    /** @return list<array<string, mixed>> */
    public function all(): array
    {
        $this->sync();
        $rows = $this->app->db->fetchAll('SELECT * FROM plugins ORDER BY name');
        $bySlug = [];
        foreach ($rows as $row) {
            $bySlug[(string) $row['slug']] = $row;
        }

        $plugins = [];
        foreach (glob($this->directory . '/*', GLOB_ONLYDIR) ?: [] as $dir) {
            $slug = basename($dir);
            $meta = $this->metadata($slug);
            if ($meta === null) {
                continue;
            }
            $plugins[] = array_merge($meta, [
                'slug'          => $slug,
                'active'        => (int) ($bySlug[$slug]['active'] ?? 0),
                'config_fields' => $meta['config'],
                'config'        => $this->config($slug),
            ]);
        }

        return $plugins;
    }

    public function sync(): void
    {
        foreach (glob($this->directory . '/*', GLOB_ONLYDIR) ?: [] as $dir) {
            $slug = basename($dir);
            $meta = $this->metadata($slug);
            if ($meta === null) {
                continue;
            }
            $exists = $this->app->db->fetch('SELECT id FROM plugins WHERE slug = ? LIMIT 1', [$slug]);
            $now = date('Y-m-d H:i:s');
            $data = [
                'name'        => $meta['name'],
                'description' => $meta['description'],
                'version'     => $meta['version'],
                'author'      => $meta['author'],
                'main'        => $meta['main'],
                'updated_at'  => $now,
            ];
            if ($exists === null) {
                $data['slug'] = $slug;
                $data['active'] = 0;
                $data['installed_at'] = $now;
                $this->app->db->insert('plugins', $data);
            } else {
                $this->app->db->update('plugins', $data, ['slug' => $slug]);
            }
        }
    }

    /** @return array<string, mixed>|null */
    public function metadata(string $slug): ?array
    {
        if (preg_match('/^[a-zA-Z0-9_-]+$/', $slug) !== 1) {
            return null;
        }
        $file = $this->directory . '/' . $slug . '/plugin.json';
        if (!is_file($file)) {
            return null;
        }
        $data = json_decode((string) file_get_contents($file), true);
        if (!is_array($data)) {
            return null;
        }

        return [
            'name'        => (string) ($data['name'] ?? $slug),
            'description' => (string) ($data['description'] ?? ''),
            'version'     => (string) ($data['version'] ?? '1.0.0'),
            'author'      => (string) ($data['author'] ?? ''),
            'main'        => (string) ($data['main'] ?? 'plugin.php'),
            'config'      => is_array($data['config'] ?? null) ? $data['config'] : [],
        ];
    }

    public function setActive(string $slug, bool $active): void
    {
        if ($this->metadata($slug) === null) {
            throw new RuntimeException('插件不存在。');
        }
        $this->app->db->update('plugins', ['active' => $active ? 1 : 0], ['slug' => $slug]);
        $this->app->cache->clear();
    }

    /** @return array<string, mixed> */
    public function config(string $slug): array
    {
        $raw = $this->app->options->get('plugin_config_' . $slug, '{}');
        $data = is_string($raw) ? json_decode($raw, true) : null;

        return is_array($data) ? $data : [];
    }

    /** @param array<string, mixed> $config */
    public function saveConfig(string $slug, array $config): void
    {
        $this->app->options->set('plugin_config_' . $slug, json_encode($config, JSON_UNESCAPED_UNICODE));
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
        $tmp = (string) ($file['tmp_name'] ?? '');
        $zip = new ZipArchive();
        if ($zip->open($tmp) !== true) {
            throw new RuntimeException('无法打开 ZIP 文件。');
        }

        $work = BASE_PATH . '/storage/cache/plugin-' . bin2hex(random_bytes(6));
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

            $root = $this->findProjectRoot($work, 'plugin.json');
            $meta = json_decode((string) file_get_contents($root . '/plugin.json'), true);
            $slug = is_array($meta) ? Str::slug((string) ($meta['slug'] ?? basename($root))) : '';
            if ($slug === '' || !is_dir($root)) {
                throw new RuntimeException('插件包缺少有效的 plugin.json。');
            }
            $target = $this->directory . '/' . $slug;
            if (is_dir($target)) {
                throw new RuntimeException('同名插件已存在。');
            }
            $this->copyDirectory($root, $target);
            $this->sync();
            $this->app->cache->clear();

            return $slug;
        } finally {
            $this->deleteDirectory($work);
        }
    }

    public function delete(string $slug): void
    {
        if (preg_match('/^[a-zA-Z0-9_-]+$/', $slug) !== 1) {
            throw new RuntimeException('插件标识无效。');
        }
        $dir = $this->directory . '/' . $slug;
        if (is_dir($dir)) {
            $this->deleteDirectory($dir);
        }
        $this->app->db->delete('plugins', ['slug' => $slug]);
        $this->app->cache->clear();
    }

    private function findProjectRoot(string $directory, string $marker): string
    {
        if (is_file($directory . '/' . $marker)) {
            return $directory;
        }
        foreach (glob($directory . '/*', GLOB_ONLYDIR) ?: [] as $child) {
            if (is_file($child . '/' . $marker)) {
                return $child;
            }
        }

        throw new RuntimeException('压缩包中没有找到插件目录。');
    }

    private function copyDirectory(string $source, string $target): void
    {
        if (!is_dir($target)) {
            @mkdir($target, 0775, true);
        }
        foreach (scandir($source) ?: [] as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }
            $from = $source . '/' . $item;
            $to = $target . '/' . $item;
            if (is_dir($from)) {
                $this->copyDirectory($from, $to);
            } else {
                copy($from, $to);
            }
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