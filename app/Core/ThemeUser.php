<?php

declare(strict_types=1);

namespace App\Core;

/**
 * Typecho-compatible user object used for `$this->user` (the visitor) and
 * `$this->author` (the author of the current post).
 */
final class ThemeUser
{
    /** @param array<string, mixed>|null $row */
    public function __construct(
        private readonly ?array $row,
        private readonly string $fallbackName = ''
    ) {
    }

    public function hasLogin(): bool
    {
        return $this->row !== null;
    }

    public function isAdmin(): bool
    {
        return ($this->row['role'] ?? '') === 'admin';
    }

    public function __get(string $name): mixed
    {
        return match ($name) {
            'uid', 'id'          => (int) ($this->row['id'] ?? 0),
            'name', 'screenName', 'displayName' => $this->displayName(),
            'username'           => (string) ($this->row['username'] ?? ''),
            'mail', 'email'      => (string) ($this->row['email'] ?? ''),
            'url'                => (string) ($this->row['url'] ?? ''),
            'group', 'role'      => (string) ($this->row['role'] ?? 'visitor'),
            default              => $this->row[$name] ?? null,
        };
    }

    public function __isset(string $name): bool
    {
        return $this->__get($name) !== null;
    }

    public function __call(string $name, array $arguments): void
    {
        $value = $this->__get($name);
        if (is_scalar($value)) {
            echo (string) $value;
        }
    }

    public function screenName(): void
    {
        echo e($this->displayName());
    }

    public function name(): void
    {
        $this->screenName();
    }

    public function mail(): void
    {
        echo e((string) $this->__get('mail'));
    }

    public function url(): void
    {
        echo e((string) $this->__get('url'));
    }

    /**
     * Typecho themes commonly call `$this->author->permalink()`. WenBlog has
     * no public author archive yet, so this falls back to the archive index.
     */
    public function permalink(): void
    {
        echo e(url('archives'));
    }

    public function avatar(int $size = 40): void
    {
        $email = strtolower(trim((string) $this->__get('mail')));
        if ($email === '') {
            return;
        }
        $source = 'https://secure.gravatar.com/avatar/' . md5($email) . '?s=' . max(16, $size) . '&d=mp';
        echo '<img class="avatar" src="' . e($source) . '" width="' . max(16, $size)
            . '" height="' . max(16, $size) . '" alt="" loading="lazy">';
    }

    private function displayName(): string
    {
        if ($this->row === null) {
            return $this->fallbackName;
        }

        $display = trim((string) ($this->row['display_name'] ?? ''));
        if ($display !== '') {
            return $display;
        }

        return (string) ($this->row['username'] ?? $this->fallbackName);
    }
}
