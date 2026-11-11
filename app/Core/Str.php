<?php

declare(strict_types=1);

namespace App\Core;

final class Str
{
    public static function slug(string $value): string
    {
        $value = trim($value);
        if ($value === '') {
            return '';
        }
        $value = mb_strtolower($value, 'UTF-8');
        $value = preg_replace('/[^\p{L}\p{N}]+/u', '-', $value) ?? '';
        $value = trim($value, '-');

        return mb_substr($value, 0, 180, 'UTF-8');
    }

    public static function random(int $bytes = 16): string
    {
        return bin2hex(random_bytes($bytes));
    }

    public static function token(int $bytes = 32): string
    {
        return rtrim(strtr(base64_encode(random_bytes($bytes)), '+/', '-_'), '=');
    }

    public static function limit(string $value, int $length): string
    {
        return mb_substr($value, 0, $length, 'UTF-8');
    }

    public static function containsCjk(string $value): bool
    {
        return preg_match('/[\x{4e00}-\x{9fff}]/u', $value) === 1;
    }

    public static function normalizeNewlines(string $value): string
    {
        return str_replace(["\r\n", "\r"], "\n", $value);
    }

    public static function maskEmail(string $email): string
    {
        if (!str_contains($email, '@')) {
            return $email;
        }
        [$name, $domain] = explode('@', $email, 2);

        return mb_substr($name, 0, 1, 'UTF-8') . '***@' . $domain;
    }
}