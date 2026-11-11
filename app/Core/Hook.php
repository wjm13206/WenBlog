<?php

declare(strict_types=1);

namespace App\Core;

use Throwable;

/**
 * Lightweight event/filter bus used by plugins and the theme layer.
 */
final class Hook
{
    /** @var array<string, array<int, list<callable>>> */
    private static array $listeners = [];

    public static function add(string $event, callable $callback, int $priority = 10): void
    {
        self::$listeners[$event][$priority][] = $callback;
    }

    /** @param mixed ...$args */
    public static function do(string $event, mixed ...$args): void
    {
        foreach (self::sorted($event) as $callback) {
            try {
                $callback(...$args);
            } catch (Throwable $e) {
                Logger::error('Hook failed: ' . $event, ['error' => $e->getMessage()]);
            }
        }
    }

    public static function filter(string $event, mixed $value, mixed ...$args): mixed
    {
        foreach (self::sorted($event) as $callback) {
            try {
                $value = $callback($value, ...$args);
            } catch (Throwable $e) {
                Logger::error('Filter failed: ' . $event, ['error' => $e->getMessage()]);
            }
        }

        return $value;
    }

    public static function has(string $event): bool
    {
        return !empty(self::$listeners[$event]);
    }

    public static function removeAll(): void
    {
        self::$listeners = [];
    }

    /** @return list<callable> */
    private static function sorted(string $event): array
    {
        $groups = self::$listeners[$event] ?? [];
        if ($groups === []) {
            return [];
        }
        ksort($groups);
        $callbacks = [];
        foreach ($groups as $list) {
            foreach ($list as $callback) {
                $callbacks[] = $callback;
            }
        }

        return $callbacks;
    }
}