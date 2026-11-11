<?php

declare(strict_types=1);

namespace App\Core;

/**
 * File-based full-page cache. Only anonymous GET requests are ever cached by
 * the front controller.
 */
final class Cache
{
    private string $directory;

    public function __construct(private readonly App $app)
    {
        $this->directory = BASE_PATH . '/storage/cache/pages';
        if (!is_dir($this->directory)) {
            @mkdir($this->directory, 0775, true);
        }
    }

    public function get(string $key): ?string
    {
        $file = $this->path($key);
        if (!is_file($file)) {
            return null;
        }

        $ttl = max(0, (int) $this->app->options->get('cache_ttl', 300));
        if ($ttl > 0 && filemtime($file) !== false && (time() - (int) filemtime($file)) > $ttl) {
            @unlink($file);

            return null;
        }

        $contents = @file_get_contents($file);

        return $contents === false ? null : $contents;
    }

    public function put(string $key, string $html): void
    {
        if (!is_dir($this->directory)) {
            @mkdir($this->directory, 0775, true);
        }
        // Keep the file cache bounded so arbitrary query strings cannot fill
        // the disk with unbounded page variants.
        if ($this->count() >= 2000) {
            $this->clear();
        }
        @file_put_contents($this->path($key), $html, LOCK_EX);
    }

    public function clear(): void
    {
        $files = glob($this->directory . '/*.html') ?: [];
        foreach ($files as $file) {
            @unlink($file);
        }
    }

    public function count(): int
    {
        return count(glob($this->directory . '/*.html') ?: []);
    }

    private function path(string $key): string
    {
        return $this->directory . '/' . hash('sha256', $key) . '.html';
    }
}