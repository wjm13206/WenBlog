<?php

declare(strict_types=1);

namespace App\Core;

use Throwable;

final class Logger
{
    /** @param array<string, mixed> $context */
    public static function error(string $message, array $context = []): void
    {
        self::write('ERROR', $message, $context);
    }

    /** @param array<string, mixed> $context */
    public static function warning(string $message, array $context = []): void
    {
        self::write('WARNING', $message, $context);
    }

    /** @param array<string, mixed> $context */
    public static function info(string $message, array $context = []): void
    {
        self::write('INFO', $message, $context);
    }

    /** @param array<string, mixed> $context */
    private static function write(string $level, string $message, array $context): void
    {
        $directory = BASE_PATH . '/storage/logs';
        if (!is_dir($directory)) {
            @mkdir($directory, 0775, true);
        }

        $uri = (string) ($_SERVER['REQUEST_URI'] ?? 'cli');
        $ip = (string) ($_SERVER['REMOTE_ADDR'] ?? '-');
        $contextString = $context === [] ? '' : ' ' . json_encode($context, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        $line = sprintf(
            "[%s] %s ip=%s uri=%s %s%s\n",
            date('Y-m-d H:i:s'),
            $level,
            $ip,
            $uri,
            $message,
            $contextString
        );

        @file_put_contents($directory . '/app-' . date('Y-m-d') . '.log', $line, FILE_APPEND | LOCK_EX);
    }

    public static function exception(Throwable $e): void
    {
        self::error($e->getMessage(), [
            'file'  => $e->getFile() . ':' . $e->getLine(),
            'trace' => $e->getTraceAsString(),
        ]);
    }
}