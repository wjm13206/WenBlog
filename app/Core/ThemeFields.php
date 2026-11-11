<?php

declare(strict_types=1);

namespace App\Core;

/**
 * Typecho-compatible custom-field bag. WenBlog has no per-post custom fields
 * yet, but themes ported from Typecho can safely read `$this->fields->name`
 * and simply receive null instead of a fatal error.
 */
final class ThemeFields
{
    /** @param array<string, mixed> $values */
    public function __construct(private readonly array $values = [])
    {
    }

    public function __get(string $name): mixed
    {
        return $this->values[$name] ?? null;
    }

    public function __isset(string $name): bool
    {
        return isset($this->values[$name]);
    }

    public function __call(string $name, array $arguments): void
    {
        $value = $this->values[$name] ?? null;
        if (is_scalar($value)) {
            echo (string) $value;
        }
    }

    public function get(string $name, mixed $default = null): mixed
    {
        return $this->values[$name] ?? $default;
    }

    /** @return array<string, mixed> */
    public function all(): array
    {
        return $this->values;
    }
}
