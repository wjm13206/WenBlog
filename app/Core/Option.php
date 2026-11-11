<?php

declare(strict_types=1);

namespace App\Core;

/**
 * Key/value option store backed by the options table. All options are loaded
 * once and cached for the lifetime of the request.
 */
final class Option
{
    /** @var array<string, string> */
    private array $data = [];
    /** @var array<string, mixed> */
    private array $defaults;
    private bool $loaded = false;

    /** @param array<string, mixed> $defaults */
    public function __construct(private readonly Database $db, array $defaults = [])
    {
        $this->defaults = $defaults;
    }

    public function get(string $key, mixed $default = null): mixed
    {
        $this->load();

        if (!array_key_exists($key, $this->data)) {
            return array_key_exists($key, $this->defaults) ? $this->defaults[$key] : $default;
        }

        return $this->data[$key];
    }

    public function set(string $key, mixed $value): void
    {
        $this->load();
        $stored = $this->stringify($value);
        $this->db->query(
            'INSERT INTO options (option_name, option_value) VALUES (?, ?)
             ON DUPLICATE KEY UPDATE option_value = VALUES(option_value)',
            [$key, $stored]
        );
        $this->data[$key] = $stored;
    }

    /** @param array<string, mixed> $values */
    public function setMany(array $values): void
    {
        foreach ($values as $key => $value) {
            $this->set((string) $key, $value);
        }
    }

    /** @return array<string, mixed> */
    public function all(): array
    {
        $this->load();

        return array_merge($this->defaults, $this->data);
    }

    public function refresh(): void
    {
        $this->loaded = false;
        $this->data = [];
    }

    private function load(): void
    {
        if ($this->loaded) {
            return;
        }
        $this->loaded = true;

        try {
            $rows = $this->db->fetchAll('SELECT option_name, option_value FROM options');
            foreach ($rows as $row) {
                $this->data[(string) $row['option_name']] = (string) $row['option_value'];
            }
        } catch (\Throwable $e) {
            // During installation the table does not exist yet.
            Logger::warning('Options could not be loaded: ' . $e->getMessage());
        }
    }

    private function stringify(mixed $value): string
    {
        if (is_bool($value)) {
            return $value ? '1' : '0';
        }
        if (is_array($value)) {
            return (string) json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        }
        if ($value === null) {
            return '';
        }

        return (string) $value;
    }
}