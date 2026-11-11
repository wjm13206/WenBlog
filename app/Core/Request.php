<?php

declare(strict_types=1);

namespace App\Core;

final class Request
{
    /** @var array<string, mixed> */
    private array $query;
    /** @var array<string, mixed> */
    private array $body;
    /** @var array<string, mixed> */
    private array $files;
    private string $path;
    private string $method;
    private string $basePath;

    private function __construct()
    {
        $this->query = $_GET;
        $this->body = $_POST;
        $this->files = $_FILES;
        $this->method = strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? 'GET'));
        $this->basePath = $this->detectBasePath();
        $this->path = $this->detectPath();
    }

    public static function capture(): self
    {
        return new self();
    }

    public function method(): string
    {
        return $this->method;
    }

    public function isPost(): bool
    {
        return $this->method === 'POST';
    }

    public function isGet(): bool
    {
        return $this->method === 'GET';
    }

    public function path(): string
    {
        return $this->path;
    }

    public function basePath(): string
    {
        return $this->basePath;
    }

    public function query(string $key, mixed $default = null): mixed
    {
        return $this->query[$key] ?? $default;
    }

    public function post(string $key, mixed $default = null): mixed
    {
        return $this->body[$key] ?? $default;
    }

    public function input(string $key, mixed $default = null): mixed
    {
        return $this->body[$key] ?? $this->query[$key] ?? $default;
    }

    /** @return array<string, mixed> */
    public function all(): array
    {
        return array_merge($this->query, $this->body);
    }

    /** @return array<string, mixed> */
    public function only(array $keys): array
    {
        $result = [];
        foreach ($keys as $key) {
            $result[$key] = $this->input($key);
        }

        return $result;
    }

    public function file(string $key): ?array
    {
        $file = $this->files[$key] ?? null;

        return is_array($file) ? $file : null;
    }

    public function header(string $name): string
    {
        $key = 'HTTP_' . strtoupper(str_replace('-', '_', $name));
        if (isset($_SERVER[$key])) {
            return (string) $_SERVER[$key];
        }
        if (strcasecmp($name, 'Content-Type') === 0) {
            return (string) ($_SERVER['CONTENT_TYPE'] ?? '');
        }

        return '';
    }

    public function ip(): string
    {
        $ip = (string) ($_SERVER['REMOTE_ADDR'] ?? '0.0.0.0');

        return filter_var($ip, FILTER_VALIDATE_IP) !== false ? $ip : '0.0.0.0';
    }

    public function userAgent(): string
    {
        return substr((string) ($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 255);
    }

    public function isAjax(): bool
    {
        return strcasecmp($this->header('X-Requested-With'), 'XMLHttpRequest') === 0;
    }

    public function wantsJson(): bool
    {
        return $this->isAjax() || str_contains(strtolower($this->header('Accept')), 'application/json');
    }

    public function isSecure(): bool
    {
        return (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
            || (int) ($_SERVER['SERVER_PORT'] ?? 80) === 443
            || strtolower($this->header('X-Forwarded-Proto')) === 'https';
    }

    public function currentUrl(): string
    {
        return url($this->path) . ($this->query === [] ? '' : '?' . http_build_query($this->query));
    }

    private function detectBasePath(): string
    {
        $script = str_replace('\\', '/', (string) ($_SERVER['SCRIPT_NAME'] ?? '/index.php'));
        $directory = rtrim(str_replace('/index.php', '', $script), '/');

        return $directory === '' ? '' : $directory;
    }

    private function detectPath(): string
    {
        $uri = (string) ($_SERVER['REQUEST_URI'] ?? '/');
        $path = parse_url($uri, PHP_URL_PATH);
        $path = is_string($path) ? $path : '/';
        $path = str_replace('\\', '/', $path);

        if ($this->basePath !== '' && str_starts_with($path, $this->basePath)) {
            $path = substr($path, strlen($this->basePath));
        }
        if (str_starts_with($path, '/index.php')) {
            $path = substr($path, strlen('/index.php'));
        }

        $path = rawurldecode($path);
        if ($path === '' || $path === false) {
            $path = '/';
        }
        if ($path !== '/') {
            $path = '/' . trim($path, '/');
        }

        return $path;
    }
}